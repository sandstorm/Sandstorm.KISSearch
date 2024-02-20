<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeName;

#[Proxy(false)]
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

        return $sanitized;
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
        // searching part
        $searchingQueryPartsSql = implode(",\n", $searchQuery->getSearchingQueryPartsAsString());
        // merging part
        $mergingParts = [];
        foreach ($searchQuery->getMergingQueryPartsAsString() as $searchResultTypeName => $mergingSql) {
            $limitParamName = SearchQuery::buildSearchResultTypeSpecificLimitQueryParameterNameFromString($searchResultTypeName);
            $mergingParts[] = <<<SQL
                (
                    $mergingSql
                    order by score desc
                    limit :$limitParamName
                )
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
