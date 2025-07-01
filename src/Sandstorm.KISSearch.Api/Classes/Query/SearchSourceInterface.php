<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;

/**
 * <b>Core extensibility API</b>
 * <br/>
 * Creates the query part that matches against fulltext indices. The results are stored in a CTE which is later...
 * <ol>
 * <li>mapped and filtered by result filters: {@link ResultFilterInterface}</li>
 * <li>grouped to the resulting items by type aggregators: {@link TypeAggregatorInterface}</li>
 * </ol>
 *
 * IMPORTANT: don't mix fulltext matching queries with other WHERE conditions when using MariaDB / MySQL.
 * @see ResultFilterInterface for explanaition
 */
interface SearchSourceInterface
{

    /**
     * @param DatabaseType $databaseType
     * @param string $sourceIdentifier
     * @param array $queryOptions
     * @param array $filterOptions
     * @return string
     */
    function getSearchingQueryPart(
        DatabaseType $databaseType,
        string $sourceIdentifier,
        array $queryOptions,
        array $filterOptions
    ): string;

    /**
     * Controls the uniqueness of this search source. In case, multiple filters require the same search source, the SQL
     * is only generated and added once to the query.
     *
     * @param DatabaseType $databaseType
     * @param string $sourceIdentifier
     * @param array $queryOptions
     * @param array $filterOptions
     * @return string
     */
    function getCTEName(
        DatabaseType $databaseType,
        string $sourceIdentifier,
        array $queryOptions,
        array $filterOptions
    ): string;

}