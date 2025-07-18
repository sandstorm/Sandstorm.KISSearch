<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\DBAbstraction;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Sandstorm\KISSearch\Api\Query\Model\SearchResult;

/**
 * Adapter for the doctrine {@link EntityManagerInterface}.
 */
final readonly class DoctrineDatabaseAdapter implements SearchQueryDatabaseAdapterInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @param string $sql
     * @param array $parameters
     * @return SearchResult[]
     */
    function executeSearchQuery(string $sql, array $parameters): array
    {
        // prepare query
        $resultSetMapping = self::buildResultSetMapping();
        $doctrineQuery = $this->entityManager->createNativeQuery($sql, $resultSetMapping);
        $doctrineQuery->setParameters($parameters);
        // fire query
        return $doctrineQuery->getResult();
    }

    private static function buildResultSetMapping(): ResultSetMapping
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('result_id', 1);
        $rsm->addScalarResult('result_type', 2);
        $rsm->addScalarResult('result_title', 3);
        $rsm->addScalarResult('result_url', 4);
        $rsm->addScalarResult('score', 5, 'float');
        $rsm->addScalarResult('match_count', 6, 'integer');
        $rsm->addScalarResult('group_meta_data', 7);
        $rsm->addScalarResult('meta_data', 8);
        $rsm->newObjectMappings['result_id'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 0,
        ];
        $rsm->newObjectMappings['result_type'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 1,
        ];
        $rsm->newObjectMappings['result_title'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 2,
        ];
        $rsm->newObjectMappings['result_url'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 3,
        ];
        $rsm->newObjectMappings['score'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 4,
        ];
        $rsm->newObjectMappings['match_count'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 5,
        ];
        $rsm->newObjectMappings['group_meta_data'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 6,
        ];
        $rsm->newObjectMappings['meta_data'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 7,
        ];
        return $rsm;
    }
}