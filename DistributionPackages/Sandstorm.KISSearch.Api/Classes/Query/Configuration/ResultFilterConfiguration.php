<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Configuration;

use Sandstorm\KISSearch\Api\Query\Model\SearchResultTypeName;

readonly class ResultFilterConfiguration
{
    /**
     * @param string $filterIdentifier
     * @param string $resultFilterReference
     * @param SearchResultTypeName $resultType
     * @param array $defaultParameters
     * @param array<string> $requiredSources
     */
    public function __construct(
        private string $filterIdentifier,
        private string $resultFilterReference,
        private SearchResultTypeName $resultType,
        private array $defaultParameters,
        private array $requiredSources
    )
    {
    }

    public static function fromConfigurationArray(string $filterIdentifier, array $filterConfiguration): self
    {
        $filterRef = $filterConfiguration['filter'] ?? null;
        if (!is_string($filterRef) || strlen(trim($filterRef)) === 0) {
            throw new \RuntimeException("Invalid search endpoint filters configuration '...filters.$filterIdentifier.filter'; value must be a string but was: " . gettype($filterRef));
        }
        $resultTypeString = $filterConfiguration['resultType'] ?? null;
        if (!is_string($resultTypeString) || strlen(trim($resultTypeString)) === 0) {
            throw new \RuntimeException("Invalid search endpoint filters configuration '...filters.$filterIdentifier.resultType'; value must be a string but was: " . gettype($resultTypeString));
        }
        $defaultParameters = $filterConfiguration['defaultParameters'] ?? null;
        if (!is_array($defaultParameters)) {
            throw new \RuntimeException("Invalid search endpoint filters configuration '...filters.$filterIdentifier.defaultParameters'; value must be an array but was: " . gettype($defaultParameters));
        }
        $sources = $filterConfiguration['sources'] ?? null;
        if (!is_array($sources)) {
            throw new \RuntimeException("Invalid search endpoint filters configuration '...filters.$filterIdentifier.sources'; value must be an array but was: " . gettype($sources));
        }
        return new self(
            filterIdentifier: $filterIdentifier,
            resultFilterReference: $filterRef,
            resultType: SearchResultTypeName::create($resultTypeString),
            defaultParameters: $defaultParameters,
            requiredSources: $sources
        );
    }

    public function getFilterIdentifier(): string
    {
        return $this->filterIdentifier;
    }

    public function getResultFilterReference(): string
    {
        return $this->resultFilterReference;
    }

    public function getResultType(): SearchResultTypeName
    {
        return $this->resultType;
    }

    public function getDefaultParameters(): array
    {
        return $this->defaultParameters;
    }

    public function getRequiredSources(): array
    {
        return $this->requiredSources;
    }

}