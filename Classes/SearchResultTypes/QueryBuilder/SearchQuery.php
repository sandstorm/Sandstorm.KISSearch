<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeName;

class SearchQuery
{
    public const ALIAS_RESULT_IDENTIFIER = 'result_id';
    public const ALIAS_RESULT_TITLE = 'result_title';
    public const ALIAS_RESULT_TYPE = 'result_type';
    public const ALIAS_SCORE = 'score';
    public const ALIAS_MATCH_COUNT = 'match_count';
    public const ALIAS_AGGREGATE_META_DATA = 'aggregate_meta_data';
    public const ALIAS_GROUP_META_DATA = 'group_meta_data';

    private readonly ResultSearchingQueryParts $searchingQueryParts;

    /**
     * @var ResultMergingQueryParts[] key is: search result type name
     */
    private readonly array $mergingQueryParts;

    /**
     * @param ResultSearchingQueryParts $searchingQueryParts
     * @param ResultMergingQueryParts[] $mergingQueryParts
     */
    public function __construct(ResultSearchingQueryParts $searchingQueryParts, array $mergingQueryParts)
    {
        $this->searchingQueryParts = $searchingQueryParts;
        $this->mergingQueryParts = $mergingQueryParts;
    }

    public static function buildSearchResultTypeSpecificLimitQueryParameterNameFromString(string $searchResultTypeName): string
    {
        return sprintf("limit_%s", $searchResultTypeName);
    }

    public function getSearchingQueryParts(): ResultSearchingQueryParts
    {
        return $this->searchingQueryParts;
    }

    public function getMergingQueryParts(): array
    {
        return $this->mergingQueryParts;
    }

}
