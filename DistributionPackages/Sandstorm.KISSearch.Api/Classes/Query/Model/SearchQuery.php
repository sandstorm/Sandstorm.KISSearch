<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Model;

use Closure;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\FrameworkAbstraction\QueryObjectInstanceProvider;
use Sandstorm\KISSearch\Api\Query\Configuration\SearchEndpointConfiguration;
use Sandstorm\KISSearch\Api\Query\InvalidEndpointConfigurationException;
use Sandstorm\KISSearch\Api\Query\QueryParameterMapper;
use Sandstorm\KISSearch\Api\Query\ResultFilterInterface;
use Sandstorm\KISSearch\Api\Query\TypeAggregatorInterface;

readonly class SearchQuery
{
    public const SQL_QUERY_PARAM_GLOBAL_LIMIT = 'global_limit';
    public const SQL_QUERY_PARAM_QUERY = 'search_query';

    public static function buildAggregatorLimitParameterName(string $searchResultTypeName): string
    {
        return sprintf('limit_%s', str_replace(['-'], '_', $searchResultTypeName));
    }

    /**
     * includes the ':' prefix.
     *
     * @param string $resultFilterIdentifier
     * @param string $parameterName
     * @return string
     */
    public static function buildFilterSpecificParameterName(
        string $resultFilterIdentifier,
        string $parameterName
    ): string {
        return sprintf('%s__%s', $resultFilterIdentifier, $parameterName);
    }

    /**
     * @param array<SearchResultTypeName> $searchResultTypes
     * @param array<string> $searchingQueryParts
     * @param array<string> $mergingQueryParts
     * @param array<string, mixed> $defaultParameters
     */
    private function __construct(
        private array $searchResultTypes,
        private array $searchingQueryParts,
        private array $mergingQueryParts,
        private array $defaultParameters,
        private QueryParameterMapper $parameterMapper
    ) {
    }

    public static function create(
        DatabaseType $databaseType,
        QueryObjectInstanceProvider $instanceProvider,
        SearchEndpointConfiguration $endpointConfiguration,
        array $queryOptionsOverride = []
    ): SearchQuery {

        // merge query options from config with explicit overrides from API
        $effectiveQueryOptions = $endpointConfiguration->getQueryOptions();
        foreach ($queryOptionsOverride as $name => $value) {
            $effectiveQueryOptions[$name] = $value;
        }

        // collect all sources and result providers and prepare query builder instances
        // sources are only added once to the query
        // group each result provider configuration by its returning "search result type"
        $resultProvidersByType = [];
        $searchingQueryParts = [];
        $defaultParameters = [];
        $resultTypeNames = [];
        $parameterMappers = [];
        /** @var $resultFilterInstances array<string, ResultFilterInterface> */
        $resultFilterInstances = [];
        /** @var $typeAggregatorInstances array<string, TypeAggregatorInterface> */
        $typeAggregatorInstances = [];
        foreach ($endpointConfiguration->getFilters() as $resultFilterConfiguration) {
            // sources
            foreach ($resultFilterConfiguration->getRequiredSources() as $sourceIdentifier) {
                if (!array_key_exists($sourceIdentifier, $searchingQueryParts)) {
                    $searchSourceInstance = $instanceProvider->getSearchSourceInstance($sourceIdentifier);
                    $searchingQueryParts[$sourceIdentifier] = $searchSourceInstance->getSearchingQueryPart(
                        $databaseType,
                        $sourceIdentifier,
                        $effectiveQueryOptions
                    );
                }
            }
            // result filters
            $resultFilterReference = $resultFilterConfiguration->getResultFilterReference();
            if (!array_key_exists($resultFilterReference, $resultFilterInstances)) {
                $resultFilterInstances[$resultFilterReference] = $instanceProvider->getResultFilterInstance(
                    $resultFilterReference
                );
            }
            $resultFilter = $resultFilterInstances[$resultFilterReference];
            $resultTypeName = $resultFilterConfiguration->getResultType()->getName();
            if (!array_key_exists($resultTypeName, $resultProvidersByType)) {
                $resultProvidersByType[$resultTypeName] = [];
                $resultTypeNames[] = SearchResultTypeName::create($resultTypeName);
                // also initialize type aggregator (there is exactly one aggregator per result type)
                $typeAggregatorReference = $endpointConfiguration->getTypeAggregators()[$resultTypeName] ?? null;
                if ($typeAggregatorReference === null) {
                    throw new InvalidEndpointConfigurationException("no type aggregator for search result type $resultTypeName");
                }
                $typeAggregatorInstances[$resultTypeName] = $instanceProvider->getTypeAggregatorInstance(
                    $typeAggregatorReference
                );
            }

            // here, the parameter names are expected NOT to be prefixed with the result filter identifier
            foreach ($resultFilterConfiguration->getDefaultParameters() as $defaultParameterName => $defaultParameterValue) {
                $fullyQualifiedParameterName = SearchQuery::buildFilterSpecificParameterName($resultFilterConfiguration->getFilterIdentifier(), $defaultParameterName);
                if (array_key_exists($fullyQualifiedParameterName, $defaultParameters)) {
                    throw new InvalidEndpointConfigurationException("duplicate default parameter '$fullyQualifiedParameterName' for endpoint {$endpointConfiguration->getEndpointIdentifier()}");
                }
                $defaultParameters[$fullyQualifiedParameterName] = $defaultParameterValue;
            }

            $parameterMappers[] = $resultFilter->getQueryParameterMapper($databaseType, $resultFilterConfiguration->getFilterIdentifier());

            $resultProvidersByType[$resultTypeName][] = $resultFilter->getFilterQueryPart(
                $databaseType,
                $resultFilterConfiguration->getFilterIdentifier(),
                $resultTypeName,
                $effectiveQueryOptions
            );
        }

        // some validation before we continue
        if (count($searchingQueryParts) === 0) {
            throw new InvalidEndpointConfigurationException("no search sources");
        }
        if (count($resultProvidersByType) === 0) {
            throw new InvalidEndpointConfigurationException("no result providers");
        }
        foreach ($resultProvidersByType as $resultTypeName => $providers) {
            if (count($providers) === 0) {
                throw new InvalidEndpointConfigurationException("no result providers for type $resultTypeName");
            }
        }

        // now the filter parts get aggregated by result type (a.k.a. merging query parts)
        $mergingQueryParts = [];
        foreach ($resultProvidersByType as $resultTypeName => $filterQueryParts) {
            $mergingQueryParts[] = $typeAggregatorInstances[$resultTypeName]->getResultTypeAggregatorQueryPart(
                $databaseType,
                $resultTypeName,
                $filterQueryParts,
                $effectiveQueryOptions
            );
        }

        return new SearchQuery(
            $resultTypeNames,
            $searchingQueryParts,
            $mergingQueryParts,
            $defaultParameters,
            QueryParameterMapper::combineMappers($parameterMappers)
        );
    }

    public function getSearchResultTypes(): array
    {
        return $this->searchResultTypes;
    }

    /**
     * @return array<string>
     */
    public function getSearchingQueryParts(): array
    {
        return $this->searchingQueryParts;
    }

    /**
     * @return array<string>
     */
    public function getMergingQueryParts(): array
    {
        return $this->mergingQueryParts;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultParameters(): array
    {
        return $this->defaultParameters;
    }

    /**
     * @return array<string, Closure>
     */
    public function getParameterMappers(): array
    {
        return $this->parameterMapper->getParameterMappers();
    }

}