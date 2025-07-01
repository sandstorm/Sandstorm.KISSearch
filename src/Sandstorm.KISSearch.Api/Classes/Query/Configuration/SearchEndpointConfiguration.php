<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Configuration;

use Sandstorm\KISSearch\Api\Query\InvalidEndpointConfigurationException;

final readonly class SearchEndpointConfiguration
{
    /**
     * @param string $endpointIdentifier
     * @param array<string, mixed> $queryOptions
     * @param array<string, ResultFilterConfiguration> $filters
     * @param array<string, TypeAggregatorConfiguration> $typeAggregators
     */
    public function __construct(
        private string $endpointIdentifier,
        private array $queryOptions,
        private array $filters,
        private array $typeAggregators
    )
    {
    }

    public static function fromConfigurationArray(string $endpointIdentifier, array $endpoints): self
    {
        if (!array_key_exists($endpointIdentifier, $endpoints)) {
            throw new InvalidEndpointConfigurationException("Search endpoint '$endpointIdentifier' not found in configuration");
        }

        $endpointConfig = $endpoints[$endpointIdentifier];
        if (!is_array($endpointConfig)) {
            throw new InvalidEndpointConfigurationException("Invalid search endpoint configuration '$endpointIdentifier'; settings must be an array but was: " . gettype($endpointConfig));
        }

        $queryOptions = $endpointConfig['queryOptions'] ?? [];
        if (!is_array($queryOptions)) {
            throw new InvalidEndpointConfigurationException("Invalid search endpoint query options configuration '$endpointIdentifier.queryOptions'; value must be an array but was: " . gettype($queryOptions));
        }

        $filtersConfig = $endpointConfig['filters'] ?? null;
        if (!is_array($filtersConfig)) {
            throw new InvalidEndpointConfigurationException("Invalid search endpoint filters configuration '$endpointIdentifier.filters'; value must be an array but was: " . gettype($filtersConfig));
        }

        $filters = [];
        foreach ($filtersConfig as $filterIdentifier => $filterConfig) {
            if (!is_string($filterIdentifier)) {
                throw new InvalidEndpointConfigurationException("Invalid search endpoint filters configuration '$endpointIdentifier.filters.$filterIdentifier'; key must be a string but was: " . gettype($filterIdentifier));
            }
            if (!is_array($filterConfig)) {
                throw new InvalidEndpointConfigurationException("Invalid search endpoint filters configuration '$endpointIdentifier.filters.$filterIdentifier'; value must be an array but was: " . gettype($filterConfig));
            }
            $filters[$filterIdentifier] = ResultFilterConfiguration::fromConfigurationArray($filterIdentifier, $filterConfig);
        }

        $typeAggregatorsConfig = $endpointConfig['typeAggregators'] ?? null;
        if (!is_array($typeAggregatorsConfig)) {
            throw new InvalidEndpointConfigurationException("Invalid search endpoint filters configuration '$endpointIdentifier.typeAggregators'; value must be an array but was: " . gettype($typeAggregatorsConfig));
        }

        // pure validation
        $typeAggregators = [];
        foreach ($typeAggregatorsConfig as $resultTypeName => $typeAggregatorConfigArray) {
            if (!is_string($resultTypeName)) {
                throw new InvalidEndpointConfigurationException("Invalid search endpoint type aggregators configuration '$endpointIdentifier.$resultTypeName.$resultTypeName'; key must be a string but was: " . gettype($resultTypeName));
            }
            if (!is_array($typeAggregatorConfigArray)) {
                throw new InvalidEndpointConfigurationException("Invalid search endpoint type aggregators configuration '$endpointIdentifier.$resultTypeName.$resultTypeName'; value must be an array but was: " . gettype($typeAggregatorConfigArray));
            }
            $typeAggregators[$resultTypeName] = TypeAggregatorConfiguration::fromConfigurationArray($resultTypeName, $typeAggregatorConfigArray);
        }

        return new self(
            endpointIdentifier: $endpointIdentifier,
            queryOptions: $queryOptions,
            filters: $filters,
            typeAggregators: $typeAggregators
        );
    }

    public function getEndpointIdentifier(): string
    {
        return $this->endpointIdentifier;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQueryOptions(): array
    {
        return $this->queryOptions;
    }

    /**
     * @return array<string, ResultFilterConfiguration>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return array<string, TypeAggregatorConfiguration>
     */
    public function getTypeAggregators(): array
    {
        return $this->typeAggregators;
    }

    /**
     * @return array<string>
     */
    public function getResultTypeNames(): array
    {
        return array_keys($this->typeAggregators);
    }
}