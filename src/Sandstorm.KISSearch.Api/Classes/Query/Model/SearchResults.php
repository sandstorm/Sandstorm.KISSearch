<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Model;

readonly class SearchResults
{

    /**
     * @param float $queryExecutionTimeInMs
     * @param array<SearchResult> $results
     */
    public function __construct(
        private float $queryExecutionTimeInMs,
        private array $results
    )
    {
    }

    public function getQueryExecutionTimeInMs(): float
    {
        return $this->queryExecutionTimeInMs;
    }

    public function getResults(): array
    {
        return $this->results;
    }

}