<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Configuration;

use Sandstorm\KISSearch\Api\Query\InvalidEndpointConfigurationException;
use Sandstorm\KISSearch\Api\Query\Model\SearchResultTypeName;

final readonly class TypeAggregatorConfiguration
{
    public function __construct(
        private SearchResultTypeName $resultType,
        private string $typeAggregatorRef,
        private array $aggregatorOptions
    )
    {
    }

    public static function fromConfigurationArray(string $resultTypeName, array $config): self
    {
        $typeAggregatorRef = $config['aggregator'] ?? null;
        if (!is_string($typeAggregatorRef)) {
            throw new InvalidEndpointConfigurationException("Invalid search endpoint type aggregators configuration '...$resultTypeName.aggregator'; value must be a string but was: " . gettype($typeAggregatorRef));
        }

        $aggregatorOptions = $config['options'] ?? [];
        if (!is_array($aggregatorOptions)) {
            throw new InvalidEndpointConfigurationException("Invalid search endpoint type aggregators configuration '...$resultTypeName.options'; value must be an array but was: " . gettype($aggregatorOptions));
        }

        return new self(
            SearchResultTypeName::fromString($resultTypeName),
            $typeAggregatorRef,
            $aggregatorOptions
        );
    }

    public function getResultType(): SearchResultTypeName
    {
        return $this->resultType;
    }

    public function getTypeAggregatorRef(): string
    {
        return $this->typeAggregatorRef;
    }

    public function getAggregatorOptions(): array
    {
        return $this->aggregatorOptions;
    }

}