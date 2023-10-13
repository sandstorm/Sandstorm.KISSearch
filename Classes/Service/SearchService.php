<?php

namespace Sandstorm\KISSearch\Service;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\Configuration\ConfigurationManager;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseType;
use Sandstorm\KISSearch\SearchResultTypes\InvalidAdditionalParameterException;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\AdditionalQueryParameterValue;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\MySQLSearchQueryBuilder;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\ResultMergingQueryParts;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\ResultSearchingQueryParts;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\SearchQuery;
use Sandstorm\KISSearch\SearchResultTypes\SearchQueryProviderInterface;
use Sandstorm\KISSearch\SearchResultTypes\SearchQueryType;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultFrontend;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeInterface;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypesRegistry;
use Sandstorm\KISSearch\SearchResultTypes\UnsupportedDatabaseException;

#[Scope('singleton')]
class SearchService
{

    // constructor injected
    private readonly SearchResultTypesRegistry $searchResultTypesRegistry;

    private readonly ConfigurationManager $configurationManager;

    private readonly EntityManagerInterface $entityManager;

    private readonly CurrentDateTimeProvider $currentDateTimeProvider;

    /**
     * @param SearchResultTypesRegistry $searchResultTypesRegistry
     * @param ConfigurationManager $configurationManager
     * @param EntityManagerInterface $entityManager
     * @param CurrentDateTimeProvider $currentDateTimeProvider
     */
    public function __construct(
        SearchResultTypesRegistry $searchResultTypesRegistry,
        ConfigurationManager      $configurationManager,
        EntityManagerInterface    $entityManager,
        CurrentDateTimeProvider   $currentDateTimeProvider
    )
    {
        $this->searchResultTypesRegistry = $searchResultTypesRegistry;
        $this->configurationManager = $configurationManager;
        $this->entityManager = $entityManager;
        $this->currentDateTimeProvider = $currentDateTimeProvider;
    }

    /**
     * Searches all sources from the registered search result types in one single SQL query.
     *
     * How limit works here:
     *  - limit is applied to the end result after sorting by score
     *
     * Example, let's say:
     *  - you have two search result types: NeosContent and Products
     *  - a limit of 20 results
     *
     * Possible search results may be:
     *  - 20 Products
     *  - 10 Products, 10 NeosContent documents
     *  - 20 NeosContent documents
     *
     * With this strategy, the most relevant results are shown, independently of their search result type.
     *
     * @param SearchQueryInput $searchQueryInput the user input
     * @param int $limit the global query result limit
     * @return SearchResult[]
     */
    public function search(SearchQueryInput $searchQueryInput, int $limit): array
    {
        return $this->internalSearch(
            $searchQueryInput,
            $this->searchResultTypesRegistry->getConfiguredSearchResultTypes(),
            // parameter initializer -> one global limit parameter
            function(array $defaultParameters) use ($limit) {
                $defaultParameters[SearchResult::SQL_QUERY_PARAM_LIMIT] = $limit;
                return $defaultParameters;
            },
            SearchQueryType::GLOBAL_LIMIT
        );
    }

    /**
     * @param SearchQueryInput $searchQueryInput
     * @param array $limitPerResultType
     * @return SearchResult[]
     */
    public function searchLimitPerResultType(SearchQueryInput $searchQueryInput, array $limitPerResultType): array
    {
        $searchResultTypes = $this->searchResultTypesRegistry->getConfiguredSearchResultTypes();
        return $this->internalSearch(
            $searchQueryInput,
            $searchResultTypes,
            // parameter initializer
            function(array $defaultParameters) use ($limitPerResultType, $searchResultTypes) {
                return self::buildLimitParametersPerSearchResultType($defaultParameters, $limitPerResultType, $searchResultTypes);
            },
            SearchQueryType::LIMIT_PER_RESULT_TYPE
        );
    }

    private static function buildLimitParametersPerSearchResultType(array $defaultParameters, array $limitPerResultType, array $searchResultTypes): array
    {
        // limit per result type
        foreach (array_keys($searchResultTypes) as $searchResultTypeName) {
            if (!array_key_exists($searchResultTypeName, $limitPerResultType) || !is_int($limitPerResultType[$searchResultTypeName])) {
                throw new MissingLimitException(
                    sprintf("Limit parameter is missing for search result type '%s'", $searchResultTypeName),
                    1697034967
                );
            }
            $defaultParameters[SearchQuery::buildSearchResultTypeSpecificLimitQueryParameterNameFromString($searchResultTypeName)] = $limitPerResultType[$searchResultTypeName];
        }
        return $defaultParameters;
    }

    /**
     * Searches all sources from the registered search result types in one single SQL query.
     * Also enriches the search results with their respective search result document URLs.
     *
     * @see search
     *
     * @param SearchQueryInput $searchQueryInput
     * @param int $limit the global query result limit
     * @return SearchResultFrontend[]
     */
    public function searchFrontend(SearchQueryInput $searchQueryInput, int $limit): array
    {
        $searchResultTypes = $this->searchResultTypesRegistry->getConfiguredSearchResultTypes();
        $results = $this->internalSearch(
            $searchQueryInput,
            $searchResultTypes,
            // parameter initializer -> one global limit parameter
            function (array $defaultParameters) use ($limit) {
                $defaultParameters[SearchResult::SQL_QUERY_PARAM_LIMIT] = $limit;
                return $defaultParameters;
            },
            SearchQueryType::GLOBAL_LIMIT
        );
        return self::enrichResultsWithDocumentUrl($results, $searchResultTypes);
    }

    public function searchFrontendLimitPerResultType(SearchQueryInput $searchQueryInput, array $limitPerResultType): array
    {
        $searchResultTypes = $this->searchResultTypesRegistry->getConfiguredSearchResultTypes();
        $results = $this->internalSearch(
            $searchQueryInput,
            $searchResultTypes,
            // parameter initializer
            function(array $defaultParameters) use ($limitPerResultType, $searchResultTypes) {
                return self::buildLimitParametersPerSearchResultType($defaultParameters, $limitPerResultType, $searchResultTypes);
            },
            SearchQueryType::LIMIT_PER_RESULT_TYPE
        );
        return self::enrichResultsWithDocumentUrl($results, $searchResultTypes);
    }

    /**
     * @param SearchResult[] $searchResults
     * @param array $searchResultTypes
     * @return SearchResultFrontend[]
     */
    private static function enrichResultsWithDocumentUrl(array $searchResults, array $searchResultTypes): array
    {
        return array_map(function (SearchResult $searchResult) use ($searchResultTypes) {
            $responsibleSearchResultType = $searchResultTypes[$searchResult->getResultTypeName()->getName()];
            $resultPageUrl = $responsibleSearchResultType->buildUrlToResultPage($searchResult);
            return $searchResult->withDocumentUrl($resultPageUrl);
        }, $searchResults);
    }

    /**
     * @param SearchQueryInput $searchQueryInput
     * @param SearchResultTypeInterface[] $searchResultTypes
     * @param Closure $parameterInitializer
     * @param SearchQueryType $searchQueryType
     * @return SearchResult[]
     */
    private function internalSearch(SearchQueryInput $searchQueryInput, array $searchResultTypes, Closure $parameterInitializer, SearchQueryType $searchQueryType): array
    {
        // setup
        $databaseType = DatabaseType::detectDatabase($this->configurationManager);

        // search query
        $searchQueryProviders = [];
        foreach ($searchResultTypes as $searchResultTypeName => $searchResultType) {
            $searchQueryProviders[$searchResultTypeName] = $searchResultType->getSearchQueryProvider($databaseType);
        }
        // prepare search term parameter from user input
        $searchTermParameterValue = self::prepareSearchTermParameterValue($databaseType, $searchQueryInput->getQuery());
        // default parameters
        $defaultParameters = [
            SearchResult::SQL_QUERY_PARAM_QUERY => $searchTermParameterValue,
            SearchResult::SQL_QUERY_PARAM_NOW_TIME => $this->currentDateTimeProvider->getCurrentDateTime()->getTimestamp()
        ];

        // parameter initializer (different limit strategies)
        $defaultParameters = $parameterInitializer($defaultParameters);

        // search type specific additional parameters
        $additionalParameters = $this->getSearchTypeSpecificAdditionalParameters(
            array_keys($defaultParameters),
            $searchQueryProviders,
            $searchQueryInput
        );
        $searchQuerySql = $this->buildSearchQuerySql($databaseType, $searchQueryProviders, $searchQueryType);

        // prepare query
        $resultSetMapping = self::buildResultSetMapping();
        $doctrineQuery = $this->entityManager->createNativeQuery($searchQuerySql, $resultSetMapping);
        $doctrineQuery->setParameters($defaultParameters);
        foreach ($additionalParameters as $parameterName => $additionalParameter) {
            $doctrineQuery->setParameter(
                $parameterName,
                $additionalParameter->getQueryParameterValue(),
                $additionalParameter->getQueryParameterType()
            );
        }

        // fire query
        return $doctrineQuery->getResult();
    }

    /**
     * @param DatabaseType $databaseType
     * @param SearchQueryProviderInterface[] $searchQueryProviders
     * @param SearchQueryType $searchQueryType
     * @return string
     */
    private function buildSearchQuerySql(DatabaseType $databaseType, array $searchQueryProviders, SearchQueryType $searchQueryType): string
    {
        // searching query parts
        $searchingQueryParts = ResultSearchingQueryParts::merging(array_map(function (SearchQueryProviderInterface $provider) {
            return $provider->getResultSearchingQueryParts();
        }, $searchQueryProviders));

        // merging query parts
        $mergingQueryParts = [];
        foreach ($searchQueryProviders as $searchResultTypeName => $provider) {
            $mergingQueryParts[$searchResultTypeName] = $provider->getResultMergingQueryParts();
        }

        $searchQuery = new SearchQuery($searchingQueryParts, $mergingQueryParts);

        return match ($databaseType) {
            DatabaseType::MYSQL, DatabaseType::MARIADB => $this->buildMySQLSearchQuerySql($searchQuery, $searchQueryType),
            DatabaseType::POSTGRES => throw new UnsupportedDatabaseException('Postgres will be supported soon <3', 1689933374),
            default => throw new UnsupportedDatabaseException(
                "Search service does not support database of type '$databaseType->name'",
                1689933081
            )
        };
    }

    private function buildMySQLSearchQuerySql(SearchQuery $searchQuery, SearchQueryType $searchQueryType): string
    {
        return match($searchQueryType) {
            SearchQueryType::GLOBAL_LIMIT => MySQLSearchQueryBuilder::searchQueryGlobalLimit($searchQuery),
            SearchQueryType::LIMIT_PER_RESULT_TYPE => MySQLSearchQueryBuilder::searchQueryLimitPerResultType($searchQuery),
            default => throw new UnsupportedSearchQueryType("Search service does not support search query type '$searchQueryType->name'", 1697203050)
        };
    }

    private static function prepareSearchTermParameterValue(DatabaseType $databaseType, string $userInput): string
    {
        return match ($databaseType) {
            DatabaseType::MYSQL, DatabaseType::MARIADB => MySQLSearchQueryBuilder::prepareSearchTermQueryParameter($userInput),
            DatabaseType::POSTGRES => throw new UnsupportedDatabaseException('Postgres will be supported soon <3', 1689936252),
            default => throw new UnsupportedDatabaseException(
                "Search service does not support database of type '$databaseType->name'",
                1689936258
            )
        };
    }

    private static function buildResultSetMapping(): ResultSetMapping
    {
        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('result_id', 1);
        $rsm->addScalarResult('result_type', 2);
        $rsm->addScalarResult('result_title', 3);
        $rsm->addScalarResult('score', 4, 'float');
        $rsm->addScalarResult('match_count', 5, 'integer');
        $rsm->addScalarResult('group_meta_data', 6);
        $rsm->addScalarResult('aggregate_meta_data', 7);
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
        $rsm->newObjectMappings['score'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 3,
        ];
        $rsm->newObjectMappings['match_count'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 4,
        ];
        $rsm->newObjectMappings['group_meta_data'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 5,
        ];
        $rsm->newObjectMappings['aggregate_meta_data'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 6,
        ];
        return $rsm;
    }

    /**
     * @param string[] $defaultParameterNames
     * @param SearchQueryProviderInterface[] $searchQueryProviders
     * @param SearchQueryInput $searchQuery
     * @return AdditionalQueryParameterValue[]
     */
    private function getSearchTypeSpecificAdditionalParameters(array $defaultParameterNames, array $searchQueryProviders, SearchQueryInput $searchQuery): array
    {
        /** @var AdditionalQueryParameterValue[] $additionalParameters */
        $additionalParameters = [];
        $additionalParameterValues = $searchQuery->getSearchTypeSpecificAdditionalParameters() ?: [];

        foreach ($searchQueryProviders as $searchResultTypeName => $searchQueryProvider) {
            $additionalQueryParametersDefinitions = $searchQueryProvider->getAdditionalQueryParameters();
            if ($additionalQueryParametersDefinitions === null) {
                continue;
            }
            foreach ($additionalQueryParametersDefinitions as $additionalQueryParametersDefinition) {
                $parameterName = $additionalQueryParametersDefinition->getParameterName();
                // check for conflicts with default parameter names
                if (in_array($parameterName, $defaultParameterNames)) {
                    throw new InvalidAdditionalParameterException(
                        "Additional query parameter '$parameterName' defined by search result type '$searchResultTypeName' "
                        . "conflicts with default parameter name; please rename it",
                        1689983919
                    );
                }
                // check for conflicts with additional parameter names from other search result types
                if (array_key_exists($parameterName, $additionalParameters)) {
                    $existingDefinition = $additionalParameters[$parameterName];
                    throw new InvalidAdditionalParameterException(
                        sprintf(
                            "Additional query parameter '%s' defined by search result type '%s' "
                            . "conflicts with existing parameter name defined by search result type '%s'; please rename it",
                            $parameterName,
                            $searchResultTypeName,
                            $existingDefinition->getParameterDefinition()->getSearchResultTypeName()->getName()
                        ),
                        1689985645
                    );
                }
                $additionalParameters[$parameterName] = $additionalQueryParametersDefinition->withParameterValue(
                    array_key_exists($parameterName, $additionalParameterValues) ? $additionalParameterValues[$parameterName] : null
                );
            }
        }

        return $additionalParameters;
    }
}
