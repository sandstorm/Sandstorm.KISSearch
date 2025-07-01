<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Schema;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;

/**
 * Creates SQL to be executed when refreshing the search dependencies.
 *
 * F.e.: in Neos Search Results, there is a table called "nodes and their documents", that needs to be refreshed on publish.
 *
 * TODO comment
 */
interface SearchDependencyRefresherInterface
{

    function refreshSearchDependencies(DatabaseType $databaseType, string $schemaIdentifier, array $options): array;

}