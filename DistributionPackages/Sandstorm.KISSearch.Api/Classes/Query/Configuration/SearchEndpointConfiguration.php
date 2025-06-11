<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Configuration;

readonly class SearchEndpointConfiguration
{
    /**
     * @param string $endpointIdentifier
     * @param array<string, ResultFilterConfiguration> $filters
     * @param array<string, string> $typeAggregators
     */
    public function __construct(
        private string $endpointIdentifier,
        private array $filters,
        private array $typeAggregators
    )
    {
        // TODO validate
    }

    public static function fromConfigurationArray(string $endpointIdentifier, array $endpoints): self
    {
        if (!array_key_exists($endpointIdentifier, $endpoints)) {
            throw new \RuntimeException("Search endpoint '$endpointIdentifier' not found in configuration");
        }

        $endpointConfig = $endpoints[$endpointIdentifier];
        if (!is_array($endpointConfig)) {
            throw new \RuntimeException("Invalid search endpoint configuration '$endpointIdentifier'; settings must be an array but was: " . gettype($endpointConfig));
        }

        $filtersConfig = $endpointConfig['filters'] ?? null;
        if (!is_array($filtersConfig)) {
            throw new \RuntimeException("Invalid search endpoint filters configuration '$endpointIdentifier.filters'; value must be an array but was: " . gettype($filtersConfig));
        }

        $filters = [];
        foreach ($filtersConfig as $filterIdentifier => $filterConfig) {
            if (!is_string($filterIdentifier)) {
                throw new \RuntimeException("Invalid search endpoint filters configuration '$endpointIdentifier.filters.$filterIdentifier'; key must be a string but was: " . gettype($filterIdentifier));
            }
            if (!is_array($filterConfig)) {
                throw new \RuntimeException("Invalid search endpoint filters configuration '$endpointIdentifier.filters.$filterIdentifier'; value must be an array but was: " . gettype($filterConfig));
            }
            $filters[$filterIdentifier] = ResultFilterConfiguration::fromConfigurationArray($filterIdentifier, $filterConfig);
        }

        $typeAggregatorsConfig = $endpointConfig['typeAggregators'] ?? null;
        if (!is_array($typeAggregatorsConfig)) {
            throw new \RuntimeException("Invalid search endpoint filters configuration '$endpointIdentifier.typeAggregators'; value must be an array but was: " . gettype($typeAggregatorsConfig));
        }

        // pure validation
        $typeAggregators = [];
        foreach ($typeAggregatorsConfig as $resultTypeName => $typeAggregatorRef) {
            if (!is_string($resultTypeName)) {
                throw new \RuntimeException("Invalid search endpoint type aggregators configuration '$endpointIdentifier.$resultTypeName.$resultTypeName'; key must be a string but was: " . gettype($resultTypeName));
            }
            if (!is_string($typeAggregatorRef)) {
                throw new \RuntimeException("Invalid search endpoint type aggregators configuration '$endpointIdentifier.$resultTypeName.$resultTypeName'; value must be a string but was: " . gettype($typeAggregatorRef));
            }
            $typeAggregators[$resultTypeName] = $typeAggregatorRef;
        }

        return new self(
            endpointIdentifier: $endpointIdentifier,
            filters: $filters,
            typeAggregators: $typeAggregators
        );
    }

    public function getEndpointIdentifier(): string
    {
        return $this->endpointIdentifier;
    }

    /**
     * @return array<string, ResultFilterConfiguration>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @return array<string, string>
     */
    public function getTypeAggregators(): array
    {
        return $this->typeAggregators;
    }
}