<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;

/**
 * Creates the SQL part, that
 */
interface TypeAggregatorInterface
{

    function getResultTypeAggregatorQueryPart(
        DatabaseType $databaseType,
        string $resultTypeName,
        array $mergingQueryParts
    ): string;

}