<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query;

use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;

readonly class QueryParameters
{
    public function __construct(
        private array $parameterMappers = []
    )
    {
    }

    /**
     * @param array<QueryParameters> $mappers
     * @return QueryParameters
     */
    public static function combineMappers(array $mappers): QueryParameters
    {
        $combinedMappers = [];
        foreach ($mappers as $mapper) {
            foreach ($mapper->parameterMappers as $parameterName => $mapperFunction) {
                if (array_key_exists($parameterName, $combinedMappers)) {
                    throw new DuplicateParameterMapperException("Parameter Mapper for '$parameterName' already defined");
                }
                $combinedMappers[$parameterName] = $mapperFunction;
            }
        }
        return new QueryParameters($combinedMappers);
    }

    public function addMapper(string $fullyQualifiedParameterName, \Closure $mapperFunction): self
    {
        $mappers = $this->parameterMappers;
        $mappers[$fullyQualifiedParameterName] = $mapperFunction;
        return new self($mappers);
    }

    public function addFilterSpecificMapper(string $filterIdentifier, string $parameterName, \Closure $mapperFunction): self
    {
        return $this->addMapper(SearchQuery::buildFilterSpecificParameterName($filterIdentifier, $parameterName), $mapperFunction);
    }

    public function getParameterMappers(): array
    {
        return $this->parameterMappers;
    }

}