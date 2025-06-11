<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\DBAbstraction;

use Sandstorm\KISSearch\Api\SearchResult;

interface SearchQueryDatabaseAdapterInterface
{

    /**
     * @param string $sql
     * @param array $parameters
     * @return array<SearchResult>
     */
    function executeSearchQuery(string $sql, array $parameters): array;

}