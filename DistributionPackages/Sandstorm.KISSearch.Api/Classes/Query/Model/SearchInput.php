<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Model;

readonly class SearchInput
{
    public function __construct(
        private string $searchQuery,
        private array $parameters
    )
    {
    }
}