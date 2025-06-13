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

    function getSearchingQueryPart(DatabaseType $databaseType, string $sourceIdentifier, array $queryOptions): string;

}