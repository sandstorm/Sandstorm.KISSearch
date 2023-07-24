<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class SearchResult implements \JsonSerializable
{

    public const SQL_QUERY_PARAM_QUERY = 'query';
    public const SQL_QUERY_PARAM_LIMIT = 'limit';
    public const SQL_QUERY_PARAM_NOW_TIME = 'nowTime';

    private readonly SearchResultIdentifier $identifier;
    private readonly SearchResultTypeName $resultTypeName;
    private readonly string $title;
    private readonly float $score;
    private readonly int $matchCount;
    private readonly array $groupMetaData;
    private readonly array $aggregateMetaData;

    /**
     * @param string $identifier
     * @param string $resultTypeName
     * @param string $title
     * @param float $score
     * @param int $matchCount
     * @param string|null $groupMetaData
     * @param string|null $aggregateMetaData
     */
    public function __construct(
        string $identifier,
        string $resultTypeName,
        string $title,
        float $score,
        int $matchCount,
        ?string $groupMetaData,
        ?string $aggregateMetaData)
    {
        $this->identifier = SearchResultIdentifier::create($identifier);
        $this->resultTypeName = SearchResultTypeName::create($resultTypeName);
        $this->title = $title;
        $this->score = $score;
        $this->matchCount = $matchCount;
        $this->groupMetaData = $groupMetaData !== null ? json_decode($groupMetaData, true) : [];
        $this->aggregateMetaData = $aggregateMetaData !== null ? array_filter(json_decode($aggregateMetaData, true)) : [];
    }

    /**
     * @param string $documentUrl
     * @return SearchResultFrontend
     */
    public function withDocumentUrl(string $documentUrl): SearchResultFrontend
    {
        return new SearchResultFrontend($this, $documentUrl);
    }

    /**
     * @return SearchResultIdentifier
     */
    public function getIdentifier(): SearchResultIdentifier
    {
        return $this->identifier;
    }

    /**
     * @return SearchResultTypeName
     */
    public function getResultTypeName(): SearchResultTypeName
    {
        return $this->resultTypeName;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @return float
     */
    public function getScore(): float
    {
        return $this->score;
    }

    /**
     * @return int
     */
    public function getMatchCount(): int
    {
        return $this->matchCount;
    }

    /**
     * @return array
     */
    public function getGroupMetaData(): array
    {
        return $this->groupMetaData;
    }

    /**
     * @return array
     */
    public function getAggregateMetaData(): array
    {
        return $this->aggregateMetaData;
    }

    public function jsonSerialize(): array
    {
        return [
            'identifier' => $this->identifier->getIdentifier(),
            'type' => $this->resultTypeName->getName(),
            'title' => $this->title,
            'score' => $this->score,
            'matchCount' => $this->matchCount,
            'groupMetaData' => $this->groupMetaData,
            'aggregateMetaData' => $this->aggregateMetaData
        ];
    }
}
