<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Model;

use Closure;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\FrameworkAbstraction\QueryObjectInstanceProvider;
use Sandstorm\KISSearch\Api\Query\Configuration\SearchEndpointConfiguration;
use Sandstorm\KISSearch\Api\Query\InvalidEndpointConfigurationException;
use Sandstorm\KISSearch\Api\Query\QueryParameters;
use Sandstorm\KISSearch\Api\Query\ResultFilterInterface;
use Sandstorm\KISSearch\Api\Query\SearchSourceInterface;
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
     * @param array<string, string> $searchingQueryParts
     * @param array<string> $mergingQueryParts
     * @param array<string, mixed> $defaultParameters
     */
    private function __construct(
        private array $searchResultTypes,
        private array $searchingQueryParts,
        private array $mergingQueryParts,
        private array $defaultParameters,
        private QueryParameters $parameterMapper
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

        // Collect all sources and filters and prepare query builder instances.
        // Sources are only added once to the query, their uniqueness is declared by their CTE name.
        // Then, group each aggregator configuration by its returning "search result type".
        $resultFiltersByType = [];
        $searchingQueryParts = [];
        $defaultParameters = [];
        $resultTypeNames = [];
        $parameterMappers = [];
        /** @var $resultFilterInstances array<string, ResultFilterInterface> */
        $resultFilterInstances = [];
        /** @var $typeAggregatorInstances array<string, TypeAggregatorInterface> */
        $typeAggregatorInstances = [];
        /** @var $searchSourceInstances array<string, SearchSourceInterface> */
        $searchSourceInstances = [];
        foreach ($endpointConfiguration->getFilters() as $resultFilterConfiguration) {
            // sources
            foreach ($resultFilterConfiguration->getRequiredSources() as $sourceIdentifier) {
                if (!array_key_exists($sourceIdentifier, $searchSourceInstances)) {
                    $searchSourceInstances[$sourceIdentifier] = $instanceProvider->getSearchSourceInstance($sourceIdentifier);
                }
                $searchSourceInstance = $searchSourceInstances[$sourceIdentifier];
                // here we make sure, that every logical search source is only added once
                $cteName = $searchSourceInstance->getCTEName($databaseType, $sourceIdentifier, $effectiveQueryOptions, $resultFilterConfiguration->getFilterOptions());
                if (!array_key_exists($cteName, $searchingQueryParts)) {
                    $searchingQueryParts[$cteName] = $searchSourceInstance->getSearchingQueryPart(
                        $databaseType,
                        $sourceIdentifier,
                        $effectiveQueryOptions,
                        $resultFilterConfiguration->getFilterOptions()
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
            if (!array_key_exists($resultTypeName, $resultFiltersByType)) {
                $resultFiltersByType[$resultTypeName] = [];
                $resultTypeNames[] = SearchResultTypeName::fromString($resultTypeName);
            }

            // here, the parameter names are expected NOT to be prefixed with the result filter identifier
            foreach ($resultFilterConfiguration->getDefaultParameters() as $defaultParameterName => $defaultParameterValue) {
                $fullyQualifiedParameterName = SearchQuery::buildFilterSpecificParameterName($resultFilterConfiguration->getFilterIdentifier(), $defaultParameterName);
                if (array_key_exists($fullyQualifiedParameterName, $defaultParameters)) {
                    throw new InvalidEndpointConfigurationException("duplicate default parameter '$fullyQualifiedParameterName' for endpoint {$endpointConfiguration->getEndpointIdentifier()}");
                }
                $defaultParameters[$fullyQualifiedParameterName] = $defaultParameterValue;
            }

            $parameterMappers[] = $resultFilter->getQueryParametersForFilter($databaseType, $resultFilterConfiguration->getFilterIdentifier());

            $resultFiltersByType[$resultTypeName][] = $resultFilter->getFilterQueryPart(
                $databaseType,
                $resultFilterConfiguration->getFilterIdentifier(),
                $resultTypeName,
                $effectiveQueryOptions,
                $resultFilterConfiguration->getFilterOptions()
            );
        }

        // TODO Maybe validate non-declared parameters that are passed in -> Fail if you misspelled the parameter name
        //      instead of silently using the default value.

        // some validation before we continue
        if (count($searchingQueryParts) === 0) {
            throw new InvalidEndpointConfigurationException("no search sources");
        }
        if (count($resultFiltersByType) === 0) {
            throw new InvalidEndpointConfigurationException("no result providers");
        }
        foreach ($resultFiltersByType as $resultTypeName => $providers) {
            if (count($providers) === 0) {
                throw new InvalidEndpointConfigurationException("no result providers for type $resultTypeName");
            }
        }

        // Now, the aggregator parts are created for each result type that is declared by any filter.

        $mergingQueryParts = [];
        foreach ($endpointConfiguration->getTypeAggregators() as $resultTypeName => $aggregatorConfiguration) {
            $typeAggregatorReference = $endpointConfiguration->getTypeAggregators()[$resultTypeName] ?? null;
            if ($typeAggregatorReference === null) {
                throw new InvalidEndpointConfigurationException("no type aggregator for search result type $resultTypeName");
            }
            // the type aggregator instances are re-used
            if (!array_key_exists($resultTypeName, $typeAggregatorInstances)) {
                $typeAggregatorInstances[$resultTypeName] = $instanceProvider->getTypeAggregatorInstance(
                    $typeAggregatorReference->getTypeAggregatorRef()
                );
            }

            $mergingQueryParts[] = $typeAggregatorInstances[$resultTypeName]->getResultTypeAggregatorQueryPart(
                $databaseType,
                $resultTypeName,
                $resultFiltersByType[$resultTypeName],
                $effectiveQueryOptions,
                $aggregatorConfiguration->getAggregatorOptions()
            );
        }

        return new SearchQuery(
            $resultTypeNames,
            $searchingQueryParts,
            $mergingQueryParts,
            $defaultParameters,
            QueryParameters::combineMappers($parameterMappers)
        );
    }

    public function getSearchResultTypes(): array
    {
        return $this->searchResultTypes;
    }

    /**
     * @return array<string, string>
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