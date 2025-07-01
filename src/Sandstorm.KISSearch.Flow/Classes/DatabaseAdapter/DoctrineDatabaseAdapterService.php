<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Flow\DatabaseAdapter;

use Doctrine\ORM\EntityManagerInterface;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Api\DBAbstraction\DoctrineDatabaseAdapter;
use Sandstorm\KISSearch\Api\DBAbstraction\SearchQueryDatabaseAdapterInterface;

#[Scope('singleton')]
class DoctrineDatabaseAdapterService implements SearchQueryDatabaseAdapterInterface
{

    protected readonly SearchQueryDatabaseAdapterInterface $delegate;

    // constructor injection
    public function __construct(
        EntityManagerInterface $entityManager
    ) {
        $this->delegate = new DoctrineDatabaseAdapter($entityManager);
    }

    function executeSearchQuery(string $sql, array $parameters): array
    {
        return $this->delegate->executeSearchQuery($sql, $parameters);
    }
}