<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeName;

#[Proxy(false)]
class DefaultResultMergingQueryPart implements ResultMergingQueryPartInterface
{
    private readonly SearchResultTypeName $resultTypeName;
    private readonly string $resultIdentifierSelector;
    private readonly string $resultTitleSelector;
    private readonly string $scoreSelector;
    private readonly ?string $resultMetaDataSelector;
    private readonly ?string $additionalSelectors;

    private readonly string $querySource;
    private readonly ?string $groupBy;


    /**
     * @param SearchResultTypeName $resultTypeName
     * @param string $resultIdentifierSelector
     * @param string $resultTitleSelector
     * @param string $scoreSelector
     * @param string|null $resultMetaDataSelector
     * @param string|null $additionalSelectors
     * @param string $querySource
     * @param string|null $groupBy
     */
    public function __construct(
        SearchResultTypeName $resultTypeName,
        string               $resultIdentifierSelector,
        string               $resultTitleSelector,
        string               $scoreSelector,
        ?string              $resultMetaDataSelector,
        ?string              $additionalSelectors,
        string               $querySource)
    {
        $this->resultTypeName = $resultTypeName;
        $this->resultIdentifierSelector = $resultIdentifierSelector;
        $this->resultTitleSelector = $resultTitleSelector;
        $this->scoreSelector = $scoreSelector;
        $this->resultMetaDataSelector = $resultMetaDataSelector;
        $this->additionalSelectors = $additionalSelectors;
        $this->querySource = $querySource;
    }

    function getMergingQueryPart(): string
    {
        $aliasResultIdentifier = SearchQuery::ALIAS_RESULT_IDENTIFIER;
        $aliasResultTitle = SearchQuery::ALIAS_RESULT_TITLE;
        $aliasResultType = SearchQuery::ALIAS_RESULT_TYPE;
        $aliasScore = SearchQuery::ALIAS_SCORE;
        $aliasAggregateMetaData = SearchQuery::ALIAS_AGGREGATE_META_DATA;

        $resultMetaDataSelector = $this->resultMetaDataSelector !== null ? $this->resultMetaDataSelector : 'null';

        $additionalSelectorsSql = $this->additionalSelectors != null ? ($this->additionalSelectors . ',') : '';

        return <<<SQL
            select
                $this->resultIdentifierSelector as $aliasResultIdentifier,
                $this->resultTitleSelector as $aliasResultTitle,
                '$this->resultTypeName' as $aliasResultType,
                $additionalSelectorsSql
                $this->scoreSelector as $aliasScore,
                $resultMetaDataSelector as $aliasAggregateMetaData
            $this->querySource
        SQL;
    }
}
