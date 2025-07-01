<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Flow\Query;

use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\DBAbstraction\MySQLHelper;
use Sandstorm\KISSearch\Api\Query\TypeAggregatorInterface;

#[Scope('singleton')]
class DefaultTypeAggregator implements TypeAggregatorInterface
{

    function getResultTypeAggregatorQueryPart(DatabaseType $databaseType, string $resultTypeName, array $mergingQueryParts, array $queryOptions): string
    {
        // TODO POSTGRES
        return MySQLHelper::buildDefaultResultTypeAggregator($resultTypeName, $mergingQueryParts, 'max(r.score)');
    }
}