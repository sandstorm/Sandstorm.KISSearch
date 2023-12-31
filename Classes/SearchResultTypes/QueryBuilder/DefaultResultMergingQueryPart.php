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
    private readonly ?string $groupMetaDataSelector;
    private readonly string $querySource;

    /**
     * @param SearchResultTypeName $resultTypeName
     * @param string $resultIdentifierSelector
     * @param string $resultTitleSelector
     * @param string $scoreSelector
     * @param string|null $resultMetaDataSelector
     * @param string|null $groupMetaDataSelector
     * @param string $querySource
     */
    public function __construct(
        SearchResultTypeName $resultTypeName,
        string               $resultIdentifierSelector,
        string               $resultTitleSelector,
        string               $scoreSelector,
        ?string              $resultMetaDataSelector,
        ?string              $groupMetaDataSelector,
        string               $querySource)
    {
        $this->resultTypeName = $resultTypeName;
        $this->resultIdentifierSelector = $resultIdentifierSelector;
        $this->resultTitleSelector = $resultTitleSelector;
        $this->scoreSelector = $scoreSelector;
        $this->resultMetaDataSelector = $resultMetaDataSelector;
        $this->groupMetaDataSelector = $groupMetaDataSelector;
        $this->querySource = $querySource;
    }

    function getMergingQueryPart(): string
    {
        $aliasResultIdentifier = SearchQuery::ALIAS_RESULT_IDENTIFIER;
        $aliasResultTitle = SearchQuery::ALIAS_RESULT_TITLE;
        $aliasResultType = SearchQuery::ALIAS_RESULT_TYPE;
        $aliasScore = SearchQuery::ALIAS_SCORE;
        $aliasMatchCount = SearchQuery::ALIAS_MATCH_COUNT;
        $aliasAggregateMetaData = SearchQuery::ALIAS_AGGREGATE_META_DATA;
        $aliasGroupMetaData = SearchQuery::ALIAS_GROUP_META_DATA;

        $resultMetaDataSelector = $this->resultMetaDataSelector !== null ? $this->resultMetaDataSelector : 'null';
        $groupMetaDataSelector = $this->groupMetaDataSelector !== null ? $this->groupMetaDataSelector : 'null';

        return <<<SQL
            select
                $this->resultIdentifierSelector as $aliasResultIdentifier,
                $this->resultTitleSelector as $aliasResultTitle,
                '$this->resultTypeName' as $aliasResultType,
                max($this->scoreSelector) as $aliasScore,
                count($this->resultIdentifierSelector) as $aliasMatchCount,
                json_arrayagg($resultMetaDataSelector) as $aliasAggregateMetaData,
                $groupMetaDataSelector as $aliasGroupMetaData
            $this->querySource
            group by $this->resultIdentifierSelector
        SQL;
    }
}
