<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Model;

readonly class SearchInput
{
    private int $globalLimit;

    public function __construct(
        private string $searchQuery,
        private array $parameters,
        private array $resultTypeLimits,
        ?int $globalLimit = null
    )
    {
        if ($globalLimit !== null) {
            $this->globalLimit = $globalLimit;
        } else {
            $this->globalLimit = array_sum($this->resultTypeLimits);
        }
    }

    public function getSearchQuery(): string
    {
        return $this->searchQuery;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function getResultTypeLimits(): array
    {
        return $this->resultTypeLimits;
    }

    public function getGlobalLimit(): int
    {
        return $this->globalLimit;
    }

}