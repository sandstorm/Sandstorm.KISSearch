<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Model;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\FrameworkAbstraction\QueryObjectInstanceProvider;
use Sandstorm\KISSearch\Api\Query\Configuration\SearchEndpointConfiguration;
use Sandstorm\KISSearch\Api\Query\ResultFilterInterface;
use Sandstorm\KISSearch\Api\Query\TypeAggregatorInterface;

readonly class SearchQuery
{
    public const SQL_QUERY_PARAM_GLOBAL_LIMIT = 'global_limit';
    public const SQL_QUERY_PARAM_QUERY = 'search_query';

    public static function buildAggregatorLimitParameterName(string $searchResultTypeName): string
    {
        return sprintf(':limit_%s', str_replace(['-'], '_', $searchResultTypeName));
    }

    public static function buildGlobalParameterName(string $parameterName): string
    {
        return sprintf(':%s', $parameterName);
    }

    public static function buildFilterSpecificParameterName(
        string $resultFilterIdentifier,
        string $parameterName
    ): string {
        return sprintf(':%s__%s', $resultFilterIdentifier, $parameterName);
    }

    /**
     * @param array<string> $searchingQueryParts
     * @param array<string> $mergingQueryParts
     */
    public function __construct(
        private array $searchingQueryParts,
        private array $mergingQueryParts
    ) {
    }

    public static function create(
        DatabaseType $databaseType,
        SearchEndpointConfiguration $endpointConfiguration,
        QueryObjectInstanceProvider $instanceProvider
    ): SearchQuery {
        // collect all sources and result providers and prepare query builder instances
        // sources are only added once to the query
        // group each result provider configuration by its returning "search result type"
        $resultProvidersByType = [];
        $searchingQueryParts = [];
        /** @var $resultProvidersInstances array<string, ResultFilterInterface> */
        $resultProvidersInstances = [];
        /** @var $typeAggregatorInstances array<string, TypeAggregatorInterface> */
        $typeAggregatorInstances = [];
        foreach ($endpointConfiguration->getFilters() as $resultFilterConfiguration) {
            // sources
            foreach ($resultFilterConfiguration->getRequiredSources() as $sourceIdentifier) {
                if (!array_key_exists($sourceIdentifier, $searchingQueryParts)) {
                    $searchSourceInstance = $instanceProvider->getSearchSourceInstance($sourceIdentifier);
                    $searchingQueryParts[$sourceIdentifier] = $searchSourceInstance->getSearchingQueryPart(
                        $databaseType
                    );
                }
            }
            // result filters
            $resultProviderReference = $resultFilterConfiguration->getResultFilterReference();
            if (!array_key_exists($resultProviderReference, $resultProvidersInstances)) {
                $resultProvidersInstances[$resultProviderReference] = $instanceProvider->getResultFilterInstance(
                    $resultProviderReference
                );
            }
            $resultProvider = $resultProvidersInstances[$resultProviderReference];
            $resultTypeName = $resultFilterConfiguration->getResultType()->getName();
            if (!array_key_exists($resultTypeName, $resultProvidersByType)) {
                $resultProvidersByType[$resultTypeName] = [];
                // also initialize type aggregator (there is exactly one aggregator per result type)
                $typeAggregatorReference = $endpointConfiguration->getTypeAggregators()[$resultTypeName] ?? null;
                if ($typeAggregatorReference === null) {
                    throw new \RuntimeException("no type aggregator for search result type $resultTypeName");
                }
                $typeAggregatorInstances[$resultTypeName] = $instanceProvider->getTypeAggregatorInstance(
                    $typeAggregatorReference
                );
            }

            $resultProvidersByType[$resultTypeName][] = $resultProvider->getFilterQueryPart(
                $databaseType,
                $resultFilterConfiguration->getFilterIdentifier(),
                $resultTypeName
            );
        }

        // some validation before we continue
        if (count($searchingQueryParts) === 0) {
            throw new \RuntimeException("no search sources");
        }
        if (count($resultProvidersByType) === 0) {
            throw new \RuntimeException("no result providers");
        }
        foreach ($resultProvidersByType as $resultTypeName => $providers) {
            if (count($providers) === 0) {
                throw new \RuntimeException("no result providers for type $resultTypeName");
            }
        }

        // now the filter parts get aggregated by result type (a.k.a. merging query parts)
        $mergingQueryParts = [];
        foreach ($resultProvidersByType as $resultTypeName => $filterQueryParts) {
            $mergingQueryParts[] = $typeAggregatorInstances[$resultTypeName]->getResultTypeAggregatorQueryPart(
                $databaseType,
                $resultTypeName,
                $filterQueryParts
            );
        }

        return new SearchQuery($searchingQueryParts, $mergingQueryParts);
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

}