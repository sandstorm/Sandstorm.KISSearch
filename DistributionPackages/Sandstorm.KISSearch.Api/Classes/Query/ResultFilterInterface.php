<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;

/**
 * Core extensibility API.
 * Allows new search result providers to be defined.
 * This should be used to add mappers and filters to search sources.
 * It creates "merging" query parts.
 *
 * TODO add documentation reference to "big picture"
 */
interface ResultFilterInterface
{

    function getFilterQueryPart(DatabaseType $databaseType, string $resultFilterIdentifier, string $resultTypeName): string;

}