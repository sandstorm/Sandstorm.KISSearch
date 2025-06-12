<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\FrameworkAbstraction;

use Sandstorm\KISSearch\Api\Query\ResultFilterInterface;
use Sandstorm\KISSearch\Api\Query\SearchSourceInterface;
use Sandstorm\KISSearch\Api\Query\TypeAggregatorInterface;

readonly class DefaultQueryObjectInstanceProvider implements QueryObjectInstanceProvider
{
    /**
     * @param array<string, SearchSourceInterface> $searchSourceInstances
     * @param array<string, ResultFilterInterface> $resultFilterInstances
     * @param array<string, TypeAggregatorInterface> $typeAggregatorInstances
     */
    public function __construct(
        private array $searchSourceInstances,
        private array $resultFilterInstances,
        private array $typeAggregatorInstances
    )
    {
    }

    function getSearchSourceInstance(string $identifier): SearchSourceInterface
    {
        $instance = $this->searchSourceInstances[$identifier] ?? null;
        if ($instance === null) {
            throw new UnknownObjectException("There is no SearchSourceInterface instance found for identifier '$identifier'");
        }
        return $instance;
    }

    function getResultFilterInstance(string $identifier): ResultFilterInterface
    {
        $instance = $this->resultFilterInstances[$identifier] ?? null;
        if ($instance === null) {
            throw new UnknownObjectException("There is no ResultFilterInterface instance found for identifier '$identifier'");
        }
        return $instance;
    }

    function getTypeAggregatorInstance(string $identifier): TypeAggregatorInterface
    {
        $instance = $this->typeAggregatorInstances[$identifier] ?? null;
        if ($instance === null) {
            throw new UnknownObjectException("There is no TypeAggregatorInterface instance found for identifier '$identifier'");
        }
        return $instance;
    }
}