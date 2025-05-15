<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\DBAbstraction;

use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;

/**
 *
 */
class MySQLHelper
{
    public static function searchQueryGlobalLimit(SearchQuery $searchQuery): string
    {
        $limitParamName = SearchQuery::buildGlobalParameterName(SearchQuery::SQL_QUERY_PARAM_GLOBAL_LIMIT);
        $sql = self::buildSearchQueryWithoutLimit($searchQuery);
        $sql .= <<<SQL
            -- global limit
            limit $limitParamName;
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
        /* TODO

            $cteAlias as (
                $query
            )

         */
        $searchingQueryPartsSql = implode(",\n", $searchQuery->getSearchingQueryParts());

        // merging part (one CTE with all merging parts combined via UNION)
        $mergingQueryPartsSql = implode(" union \n", $searchQuery->getMergingQueryParts());

        return <<<SQL
            -- searching query part
            with $searchingQueryPartsSql,
                 all_results as (
                    -- union of all search result types aggregated
                    $mergingQueryPartsSql
                 )
            select
                -- select all search results
                a.result_id as result_id,
                a.result_type as result_type,
                a.result_title as result_title,
                a.score as score,
                a.match_count as match_count,
                a.group_meta_data as group_meta_data,
                a.meta_data as meta_data
            from all_results a
            order by score desc
        SQL;
    }

    /**
     * Builds an aggregator query part for all merging query parts for a given result type.
     *
     * Default mode:
     *  - multiple merging query parts are combined using "UNION"
     *  - groups by result id
     *  - max score wins
     *  - aggregates meta data for entries via json_arrayagg
     *
     * @param string $resultTypeName
     * @param array $mergingQueryParts
     * @param string $groupMetadataSelector
     * @return string
     */
    public static function buildDefaultResultTypeAggregator(
        string $resultTypeName,
        array $mergingQueryParts,
        string $groupMetadataSelector = 'null'
    ): string {
        // The merging query parts for each result type are combined using SQL 'union'.
        // Also they are enclosed in parentheses, this can be used to apply a search result
        // type specific limit later.
        $mergingPartsForTypeUnion = implode(' union ', $mergingQueryParts);

        $limitParamName = SearchQuery::buildAggregatorLimitParameterName($resultTypeName);

        return <<<SQL
                (select
                    r.result_type                           as result_type,
                    r.result_id                             as result_id,
                    r.result_title                          as result_title,
                    max(r.score)                            as score,
                    count(r.result_id)                      as match_count,
                    json_arrayagg(r.meta_data)              as meta_data,
                    $groupMetadataSelector                  as group_meta_data
                from ($mergingPartsForTypeUnion) r
                group by r.result_id
                order by r.score desc
                limit $limitParamName)
            SQL;
    }

    // --------------- indexing helpers

    public static function extractNormalizedFulltextFromJson(string $valueSql, string $jsonKey): string
    {
        return self::normalizeFulltext(
            <<<SQL
                json_extract($valueSql, '$.$jsonKey.value')
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
        $pattern = "(^.*?<$tagNamesPattern.*?>)|(<\\\\\\\\?/$tagNamesPattern.*?>.*?<$tagNamesPattern.*?>)|(<\\\\\\\\?/$tagNamesPattern.*?>.*?$)";

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

}