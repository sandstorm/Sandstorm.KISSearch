<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeName;

class SearchQuery
{
    public const ALIAS_RESULT_IDENTIFIER = 'result_id';
    public const ALIAS_RESULT_TITLE = 'result_title';
    public const ALIAS_RESULT_TYPE = 'result_type';
    public const ALIAS_SCORE = 'score';
    public const ALIAS_RESULT_META_DATA = 'result_meta_data';
    public const ALIAS_GROUP_META_DATA = 'group_meta_data';

    private readonly ResultSearchingQueryParts $searchingQueryParts;
    private readonly ResultMergingQueryParts $mergingQueryParts;

    /**
     * @param ResultSearchingQueryParts $searchingQueryParts
     * @param ResultMergingQueryParts $mergingQueryParts
     */
    public function __construct(ResultSearchingQueryParts $searchingQueryParts, ResultMergingQueryParts $mergingQueryParts)
    {
        $this->searchingQueryParts = $searchingQueryParts;
        $this->mergingQueryParts = $mergingQueryParts;
    }

    /**
     * @return string[]
     */
    public function getSearchingQueryPartsAsString(): array
    {
        $result = [];
        /** @var ResultSearchingQueryPartInterface $searchingQueryPart */
        foreach ($this->searchingQueryParts as $searchingQueryPart) {
            $result[] = $searchingQueryPart->getSearchingQueryPart();
        }
        return $result;
    }

    /**
     * @return string[]
     */
    public function getMergingQueryPartsAsString(): array
    {
        $result = [];
        /** @var ResultMergingQueryPartInterface $mergingQueryPart */
        foreach ($this->mergingQueryParts as $mergingQueryPart) {
            $result[] = $mergingQueryPart->getMergingQueryPart();
        }
        return $result;
    }

    public static function buildSearchResultTypeSpecificLimitQueryParameterName(SearchResultTypeName $searchResultTypeName): string
    {
        return self::buildSearchResultTypeSpecificLimitQueryParameterNameFromString($searchResultTypeName->getName());
    }

    public static function buildSearchResultTypeSpecificLimitQueryParameterNameFromString(string $searchResultTypeName): string
    {
        return sprintf("limit_%s", $searchResultTypeName);
    }

}
