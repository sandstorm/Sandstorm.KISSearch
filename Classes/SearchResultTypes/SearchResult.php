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
    private readonly ?array $metaData;

    /**
     * @param string $identifier
     * @param string $resultTypeName
     * @param string $title
     * @param float $score
     * @param string|null $metaData
     */
    public function __construct(string $identifier, string $resultTypeName, string $title, float $score, ?string $metaData)
    {
        $this->identifier = SearchResultIdentifier::create($identifier);
        $this->resultTypeName = SearchResultTypeName::create($resultTypeName);
        $this->title = $title;
        $this->score = $score;
        $this->metaData = $metaData !== null ? json_decode($metaData, true) : null;
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
     * @return array|null
     */
    public function getMetaData(): ?array
    {
        return $this->metaData;
    }

    public function jsonSerialize(): array
    {
        return [
            'identifier' => $this->identifier->getIdentifier(),
            'type' => $this->resultTypeName->getName(),
            'title' => $this->title,
            'score' => $this->score,
            'metaData' => $this->metaData
        ];
    }
}