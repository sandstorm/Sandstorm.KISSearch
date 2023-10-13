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
     * @return string[] key is: search result type name
     */
    public function getMergingQueryPartsAsString(): array
    {
        $result = [];
        /** @var ResultMergingQueryPartInterface[] $mergingQueryPartsForResultType */
        foreach ($this->mergingQueryParts as $searchResultTypeName => $mergingQueryPartsForResultType) {
            // The merging query parts for each result type are combined using SQL 'union'.
            // Also they are enclosed in parentheses, this can be used to apply a search result
            // type specific limit later.
            $partsAsString = [];
            foreach ($mergingQueryPartsForResultType as $part) {
                $partsAsString[] = $part->getMergingQueryPart();
            }
            $result[$searchResultTypeName] = sprintf('(%s)', implode(' union ', $partsAsString));
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
