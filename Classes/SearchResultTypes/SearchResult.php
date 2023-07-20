<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class SearchResult
{

    private readonly SearchResultIdentifier $identifier;
    private readonly SearchResultTypeName $resultTypeName;
    private readonly string $title;
    private readonly float $score;

    /**
     * @param SearchResultIdentifier $identifier
     * @param SearchResultTypeName $resultTypeName
     * @param string $title
     * @param float $score
     */
    public function __construct(SearchResultIdentifier $identifier, SearchResultTypeName $resultTypeName, string $title, float $score)
    {
        $this->identifier = $identifier;
        $this->resultTypeName = $resultTypeName;
        $this->title = $title;
        $this->score = $score;
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

}
