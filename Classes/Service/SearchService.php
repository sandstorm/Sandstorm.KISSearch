<?php

namespace Sandstorm\KISSearch\Service;

use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Sandstorm\KISSearch\PostgresTS\PostgresFulltextSearchConfiguration;
use Sandstorm\KISSearch\PostgresTS\PostgresFulltextSearchMode;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseType;
use Sandstorm\KISSearch\SearchResultTypes\InvalidAdditionalParameterException;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\AdditionalQueryParameterValue;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\MySQLSearchQueryBuilder;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\PostgresSearchQueryBuilder;
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

    // BEGIN: public API

    /**
     * Searches all sources from the registered search result types.
     * The in one single SQL query involved in loading results. An overall score is used
     * for sorting the result items.
     *
     * How limit works here:
     * A "global" limit is applied to the merged end result after sorting by score.
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
     * That means, f.e. lots of very important results from one type may "push out" results from other types.
     * If you want to see at least some items of all search result types: @see searchLimitPerResultType
     *
     * With this strategy, the most relevant results are shown, independently of their search result type.
     *
     * @param SearchQueryInput $searchQueryInput the user input
     * @param int $limit the global query result limit
     * @return SearchResult[]
     */
    public function search(SearchQueryInput $searchQueryInput, int $limit): array
    {
        $searchResultTypes = $this->searchResultTypesRegistry->getConfiguredSearchResultTypes();
        return $this->internalSearch(
            $searchQueryInput,
            $searchResultTypes,
            // parameter initializer -> one global limit parameter
            function(array $defaultParameters) use ($limit, $searchResultTypes) {
                $defaultParameters[SearchResult::SQL_QUERY_PARAM_LIMIT] = $limit;
                // internally, each result type is also limited using the global limit to improve search performance
                return self::buildLimitParametersForGlobalLimit($defaultParameters, $limit, $searchResultTypes);
            },
            SearchQueryType::GLOBAL_LIMIT
        );
    }

    /**
     * Searches all sources from the registered search result types.
     * The in one single SQL query involved in loading results.
     * The search result limits can be specified individually for each result type.
     *
     * How limits work here:
     *  - limit is applied to each result set separately
     *  - no "global" limit is explicitly applied to the merged end results
     *  - the max number of results can not be more than the *sum* of the given result-specific limits
     *  - that means, f.e. lots of very important results from one type cannot "push out" results from less
     *    important types (in other words, you may get the most important results from all types)
     *  - if you want to have a global limit based on overall score, you should probably use @see search
     *
     * @param SearchQueryInput $searchQueryInput
     * @param array $limitPerResultType the limit per search result
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
            function (array $defaultParameters) use ($limit, $searchResultTypes) {
                $defaultParameters[SearchResult::SQL_QUERY_PARAM_LIMIT] = $limit;
                return self::buildLimitParametersForGlobalLimit($defaultParameters, $limit, $searchResultTypes);
            },
            SearchQueryType::GLOBAL_LIMIT
        );
        return self::enrichResultsWithDocumentUrl($results, $searchResultTypes);
    }

    /**
     * Searches all sources from the registered search result types in one single SQL query.
     * Also enriches the search results with their respective search result document URLs.
     *
     * @see searchLimitPerResultType
     *
     * @param SearchQueryInput $searchQueryInput
     * @param array $limitPerResultType
     * @return SearchResultFrontend[]
     */
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

    // END: public API

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

    private static function buildLimitParametersForGlobalLimit(array $defaultParameters, int $limit, array $searchResultTypes): array
    {
        // limit per result type
        foreach (array_keys($searchResultTypes) as $searchResultTypeName) {
            $defaultParameters[SearchQuery::buildSearchResultTypeSpecificLimitQueryParameterNameFromString($searchResultTypeName)] = $limit;
        }
        return $defaultParameters;
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
     * @throws InvalidConfigurationTypeException
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
            SearchResult::SQL_QUERY_PARAM_NOW_TIME => $this->currentDateTimeProvider->getCurrentDateTime()->getTimestamp(),
            SearchResult::SQL_QUERY_PARAM_LANGUAGE => $searchQueryInput->getLanguage() ?: $this->getDefaultLanguage($databaseType)
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
     * @throws InvalidConfigurationTypeException
     */
    private function getDefaultLanguage(DatabaseType $databaseType): ?string
    {
        if ($databaseType !== DatabaseType::POSTGRES) {
            // for now, only postgres supports language-specific fulltext search
            return null;
        }
        $searchConfiguration = PostgresFulltextSearchConfiguration::fromSettings($this->configurationManager);
        if ($searchConfiguration->getMode() === PostgresFulltextSearchMode::CONTENT_DIMENSION) {
            // For content dimension mode, there is no default value for the language. Users must set the language via API explicitly.
            throw new MissingLanguageException("No language set for postgres search query; no default language available for mode 'contentDimension'. Please specify the language via API.", 1708432220);
        }
        return $searchConfiguration->getDefaultTsConfig();
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
            DatabaseType::POSTGRES => $this->buildPostgresSearchQuerySql($searchQuery, $searchQueryType),
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

    private function buildPostgresSearchQuerySql(SearchQuery $searchQuery, SearchQueryType $searchQueryType): string
    {
        return match($searchQueryType) {
            SearchQueryType::GLOBAL_LIMIT => PostgresSearchQueryBuilder::searchQueryGlobalLimit($searchQuery),
            SearchQueryType::LIMIT_PER_RESULT_TYPE => PostgresSearchQueryBuilder::searchQueryLimitPerResultType($searchQuery),
            default => throw new UnsupportedSearchQueryType("Search service does not support search query type '$searchQueryType->name'", 1697203051)
        };
    }

    private static function prepareSearchTermParameterValue(DatabaseType $databaseType, string $userInput): string
    {
        return match ($databaseType) {
            DatabaseType::MYSQL, DatabaseType::MARIADB => MySQLSearchQueryBuilder::prepareSearchTermQueryParameter($userInput),
            DatabaseType::POSTGRES => PostgresSearchQueryBuilder::prepareSearchTermQueryParameter($userInput),
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
