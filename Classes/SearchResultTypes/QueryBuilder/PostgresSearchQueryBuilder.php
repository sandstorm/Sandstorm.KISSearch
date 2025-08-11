<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeName;

/**
 * @Proxy(false)
 */
class PostgresSearchQueryBuilder
{
    const SPECIAL_CHARACTERS = '-+~/<>\'":*$#@()!,.?`=%&^';

    public static function buildInsertOrUpdateVersionHashQuery(string $searchResultTypeName, string $versionHash): string
    {
        // TODO SQL-injection possible! Although the values does not come from client / user input, maybe use parameters
        return <<<SQL
            insert into sandstorm_kissearch_migration_status (search_result_type_name, version_hash)
            values ('$searchResultTypeName', '$versionHash')
            on conflict (search_result_type_name) do update set version_hash = '$versionHash';
        SQL;
    }

    public static function buildDatabaseVersionQuery(): string
    {
        return <<<SQL
            select version() as version;
        SQL;
    }

    public static function prepareSearchTermQueryParameter(string $userInput): string
    {
        $sanitized = trim($userInput);
        $sanitized = mb_strtolower($sanitized);
        $sanitized = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $sanitized);

        $specialChars = str_split(self::SPECIAL_CHARACTERS);
        $sanitized = str_replace($specialChars, ' ', $sanitized);

        $searchWords = explode(
            ' ',
            $sanitized
        );

        $searchWords = array_filter($searchWords, static fn (string $searchWord) => trim($searchWord) !== '');

        $searchWordsFuzzy = array_map(static fn(string $searchWord) => '' . $searchWord . ':*', $searchWords);

        return implode(' & ', $searchWordsFuzzy);
    }

    public static function searchQueryGlobalLimit(SearchQuery $searchQuery): string
    {
        $limitParamName = SearchResult::SQL_QUERY_PARAM_LIMIT;
        $sql = self::buildSearchQueryWithoutLimit($searchQuery);
        $sql .= <<<SQL
            -- global limit
            limit :$limitParamName;
        SQL;
        return $sql;
    }

    public static function searchQueryLimitPerResultType(SearchQuery $searchQuery): string
    {
        $sql = self::buildSearchQueryWithoutLimit($searchQuery);
        $sql .= ';';
        return $sql;
    }

    private static function buildSearchQueryWithoutLimit(SearchQuery $searchQuery): string
    {
        // searching part (CTEs, comma-separated)
        $searchingQueryPartsAsStrings = [];
        /** @var ResultSearchingQueryPartInterface $searchingQueryPart */
        foreach ($searchQuery->getSearchingQueryParts() as $searchingQueryPart) {
            $searchingQueryPartsAsStrings[] = $searchingQueryPart->getSearchingQueryPart();
        }
        $searchingQueryPartsSql = implode(",\n", $searchingQueryPartsAsStrings);

        // merging part (one CTE with all merging parts combined via UNION)
        $mergingParts = [];
        foreach ($searchQuery->getMergingQueryParts() as $searchResultTypeName => $mergingQueryPartsForResultType) {
            // The merging query parts for each result type are combined using SQL 'union'.
            // Also they are enclosed in parentheses, this can be used to apply a search result
            // type specific limit later.
            $limitParamName = SearchQuery::buildSearchResultTypeSpecificLimitQueryParameterNameFromString($searchResultTypeName);
            $partsAsString = [];
            foreach ($mergingQueryPartsForResultType->getValues() as $part) {
                $partsAsString[] = $part->getMergingQueryPart();
            }
            $mergingPartsForType = implode(' union ', $partsAsString);
            $groupMetadataSelector = $mergingQueryPartsForResultType->getGroupMetadataSelector();
            if ($groupMetadataSelector === null) {
                $groupMetadataSelector = 'null';
            }
            $groupBy = $mergingQueryPartsForResultType->getGroupBy();
            if ($groupBy === null) {
                $groupBy = 'r.result_id';
            }
            $mergingParts[$searchResultTypeName] = <<<SQL
                (select
                    r.result_id                             as result_id,
                    r.result_title                          as result_title,
                    r.result_type                           as result_type,
                    max(r.score)                            as score,
                    count(r.result_id)                      as match_count,
                    json_arrayagg(r.aggregate_meta_data)    as aggregate_meta_data,
                    $groupMetadataSelector                  as group_meta_data
                from ($mergingPartsForType) r
                group by $groupBy
                order by r.score desc
                limit :$limitParamName)
            SQL;
        }
        $mergingQueryPartsSql = implode(" union \n", $mergingParts);

        $aliasResultIdentifier = SearchQuery::ALIAS_RESULT_IDENTIFIER;
        $aliasResultTitle = SearchQuery::ALIAS_RESULT_TITLE;
        $aliasResultType = SearchQuery::ALIAS_RESULT_TYPE;
        $aliasScore = SearchQuery::ALIAS_SCORE;
        $aliasMatchCount = SearchQuery::ALIAS_MATCH_COUNT;
        $aliasAggregateMetaData = SearchQuery::ALIAS_AGGREGATE_META_DATA;
        $aliasGroupMetaData = SearchQuery::ALIAS_GROUP_META_DATA;

        return <<<SQL
            -- searching query part
            with $searchingQueryPartsSql,
                 all_results as (
                    -- union of all search types
                    $mergingQueryPartsSql
                 )
            select
                -- select all search results
                a.$aliasResultIdentifier as result_id,
                a.$aliasResultType as result_type,
                a.$aliasResultTitle as result_title,
                a.$aliasScore as score,
                a.$aliasMatchCount as match_count,
                a.$aliasGroupMetaData as group_meta_data,
                a.$aliasAggregateMetaData as aggregate_meta_data
            from all_results a
            order by score desc
        SQL;
    }

}
