<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class SearchResultFrontend implements \JsonSerializable
{

    private readonly SearchResult $searchResult;
    private readonly string $documentUrl;

    /**
     * @param SearchResult $searchResult
     * @param string $documentUrl
     */
    public function __construct(SearchResult $searchResult, string $documentUrl)
    {
        $this->searchResult = $searchResult;
        $this->documentUrl = $documentUrl;
    }

    /**
     * @return SearchResult
     */
    public function getSearchResult(): SearchResult
    {
        return $this->searchResult;
    }

    /**
     * @return string
     */
    public function getDocumentUrl(): string
    {
        return $this->documentUrl;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->searchResult->getIdentifier()->getIdentifier();
    }

    /**
     * @return string
     */
    public function getResultTypeName(): string
    {
        return $this->searchResult->getResultTypeName()->getName();
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->searchResult->getTitle();
    }

    /**
     * @return float
     */
    public function getScore(): float
    {
        return $this->searchResult->getScore();
    }

    /**
     * @return int
     */
    public function getMatchCount(): int
    {
        return $this->searchResult->getMatchCount();
    }

    /**
     * @return array
     */
    public function getGroupMetaData(): array
    {
        return $this->searchResult->getGroupMetaData();
    }

    /**
     * @return array
     */
    public function getAggregateMetaData(): array
    {
        return $this->searchResult->getAggregateMetaData();
    }

    public function jsonSerialize(): array
    {
        return  [
            'identifier' => $this->getIdentifier(),
            'type' => $this->getResultTypeName(),
            'title' => $this->getTitle(),
            'url' => $this->documentUrl,
            'score' => $this->getScore(),
            'matchCount' => $this->getMatchCount(),
            'groupMetaData' => $this->getGroupMetaData(),
            'aggregateMetaData' => $this->getAggregateMetaData()
        ];
    }
}
