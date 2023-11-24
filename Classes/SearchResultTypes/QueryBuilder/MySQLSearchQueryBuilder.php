<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeName;

#[Proxy(false)]
class MySQLSearchQueryBuilder
{

    const SPECIAL_CHARACTERS = '-+~/<>\'":*$#@()!,.?`=%&^';

    public static function createFulltextIndex(SearchResultTypeName $searchResultTypeName, string $tableName, ColumnNamesByBucket $columnNames): string
    {
        // combined index
        $sqlQueries = [
            self::createSingleFulltextIndex($searchResultTypeName, $tableName, $columnNames->getAllColumnNames(), 'all')
        ];
        // single bucket index for weighting scores on individual columns
        $sqlQueries[] = self::createSingleFulltextIndex($searchResultTypeName, $tableName, $columnNames->getCritical(), 'critical');
        $sqlQueries[] = self::createSingleFulltextIndex($searchResultTypeName, $tableName, $columnNames->getMajor(), 'major');
        $sqlQueries[] = self::createSingleFulltextIndex($searchResultTypeName, $tableName, $columnNames->getNormal(), 'normal');
        $sqlQueries[] = self::createSingleFulltextIndex($searchResultTypeName, $tableName, $columnNames->getMinor(), 'minor');

        return implode("\n", array_filter($sqlQueries));
    }

    private static function createSingleFulltextIndex(SearchResultTypeName $searchResultTypeName, string $tableName, ?array $columnNames, string $indexSuffix): ?string
    {
        if (empty($columnNames)) {
            return null;
        }
        $columnNamesCommaSeparated = implode(', ', $columnNames);
        $indexSuffix = self::buildFulltextIndexName($searchResultTypeName, $indexSuffix);
        return <<<SQL
            create fulltext index $indexSuffix on $tableName ($columnNamesCommaSeparated);
        SQL;
    }

    public static function dropFulltextIndex(SearchResultTypeName $searchResultTypeName, string $tableName): string
    {
        $sqlQueries = [
            // combined index
            self::dropSingleFulltextIndex($searchResultTypeName, $tableName, 'all'),
            // single bucket index for weighting scores on individual columns
            $sqlQueries[] = self::dropSingleFulltextIndex($searchResultTypeName, $tableName, 'critical'),
            $sqlQueries[] = self::dropSingleFulltextIndex($searchResultTypeName, $tableName, 'major'),
            $sqlQueries[] = self::dropSingleFulltextIndex($searchResultTypeName, $tableName, 'normal'),
            $sqlQueries[] = self::dropSingleFulltextIndex($searchResultTypeName, $tableName, 'minor')
        ];

        return implode("\n", $sqlQueries);
    }

    private static function dropSingleFulltextIndex(SearchResultTypeName $searchResultTypeName, string $tableName, string $indexSuffix): string
    {
        $indexName = self::buildFulltextIndexName($searchResultTypeName, $indexSuffix);
        return <<<SQL
            drop index if exists $indexName on $tableName;
        SQL;
    }

    /**
     * @param SearchResultTypeName $searchResultTypeName
     * @param string $indexSuffix
     * @return string
     */
    private static function buildFulltextIndexName(SearchResultTypeName $searchResultTypeName, string $indexSuffix): string
    {
        return sprintf("idx_%s_%s", $searchResultTypeName->getName(), $indexSuffix);
    }

    public static function extractNormalizedFulltextFromJson(string $valueSql, string $jsonKey): string
    {
        return self::normalizeFulltext(
            <<<SQL
                json_extract($valueSql, '$.$jsonKey')
            SQL
        );
    }

    public static function normalizeFulltext(string $valueSql): string
    {
        return <<<SQL
            replace(replace(replace(replace(
                lower($valueSql),
                'ä','ae'),'ö','oe'), 'ü','ue'), 'ß','ss')
        SQL;
    }

    public static function extractAllText(string $valueSql): string
    {
        return self::sanitizeFulltextExtractionResult($valueSql);
    }

    public static function fulltextExtractHtmlTagContents(string $valueSql, string ...$tagNames): string
    {
        $tagNamesPattern = sprintf('(%s)', implode('|', $tagNames));
        $pattern = "(^.*?<$tagNamesPattern>)|(<\\\\\\\\?/$tagNamesPattern>.*?<$tagNamesPattern>)|(<\\\\\\\\?/$tagNamesPattern>.*?$)";

        return self::sanitizeFulltextExtractionResult(<<<SQL
            -- remove everything between closing and opening tags
            if(regexp_instr($valueSql, '$pattern'), regexp_replace($valueSql, '$pattern', ' '), null)
        SQL);
    }

    public static function fulltextExtractHtmlTextContent(string $valueSql, string ...$excludedTags): string
    {
        $tagNamesPattern = sprintf('(%s)', implode('|', $excludedTags));
        $tagContentPattern = sprintf('<%s.*?>.*?<\\\\\\\\?/%s>', $tagNamesPattern, $tagNamesPattern);
        return self::sanitizeFulltextExtractionResult(<<<SQL
            -- remove all excluded tags
            regexp_replace($valueSql, '$tagContentPattern', '')
        SQL);
    }

    private static function sanitizeFulltextExtractionResult($valueSql): string
    {
        return <<<SQL
            -- remove all duplicate whitespaces
            regexp_replace(
                -- remove all non word characters
                regexp_replace(
                    -- remove all HTML entities
                    regexp_replace(
                        -- remove all HTML tags
                        regexp_replace(
                            $valueSql,
                        '<.*?>', ' '),
                    '&.*?;', ' '),
                '[^\\\.a-zA-Z0-9]', ' '),
            '\\\s+', ' ')
        SQL;
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

        $searchWords = array_filter($searchWords, function(string $searchWord) {
            return strlen(trim($searchWord)) > 0;
        });

        $searchWordsFuzzy = array_map(function(string $searchWord) {
            return $searchWord . '*';
        }, $searchWords);

        return implode(' ', $searchWordsFuzzy);
    }

    public static function buildInsertOrUpdateVersionHashQuery(string $searchResultTypeName, string $versionHash): string
    {
        // TODO SQL-injection possible! Although the values does not come from client / user input, maybe use parameters
        return <<<SQL
            insert into sandstorm_kissearch_migration_status (search_result_type_name, version_hash)
            values ('$searchResultTypeName', '$versionHash')
            on duplicate key update version_hash = '$versionHash';
        SQL;
    }

    public static function buildDatabaseVersionQuery(): string
    {
        return <<<SQL
            SELECT @@version as version;
        SQL;
    }

}
