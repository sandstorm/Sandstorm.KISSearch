<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class SearchResult implements \JsonSerializable
{

    public const SQL_QUERY_PARAM_QUERY = 'query';
    public const SQL_QUERY_PARAM_LIMIT = 'limit';

    private readonly SearchResultIdentifier $identifier;
    private readonly SearchResultTypeName $resultTypeName;
    private readonly string $title;
    private readonly float $score;

    /**
     * @param string $identifier
     * @param string $resultTypeName
     * @param string $title
     * @param float $score
     */
    public function __construct(string $identifier, string $resultTypeName, string $title, float $score)
    {
        $this->identifier = SearchResultIdentifier::create($identifier);
        $this->resultTypeName = SearchResultTypeName::create($resultTypeName);
        $this->title = $title;
        $this->score = $score;
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

    public function jsonSerialize(): array
    {
        return [
            'identifier' => $this->identifier->getIdentifier(),
            'type' => $this->resultTypeName->getName(),
            'title' => $this->title,
            'score' => $this->score
        ];
    }
}
