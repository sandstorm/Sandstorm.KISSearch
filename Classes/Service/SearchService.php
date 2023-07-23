<?php

namespace Sandstorm\KISSearch\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\Configuration\ConfigurationManager;
use Sandstorm\KISSearch\SearchResultTypes\AdditionalQueryParameterValue;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseType;
use Sandstorm\KISSearch\SearchResultTypes\InvalidAdditionalParameterException;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\MySQLSearchQueryBuilder;
use Sandstorm\KISSearch\SearchResultTypes\SearchQueryProviderInterface;
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
    public function __construct(SearchResultTypesRegistry $searchResultTypesRegistry, ConfigurationManager $configurationManager, \Doctrine\ORM\EntityManagerInterface $entityManager, CurrentDateTimeProvider $currentDateTimeProvider)
    {
        $this->searchResultTypesRegistry = $searchResultTypesRegistry;
        $this->configurationManager = $configurationManager;
        $this->entityManager = $entityManager;
        $this->currentDateTimeProvider = $currentDateTimeProvider;
    }

    /**
     * Searches all sources from the registered search result types in one single SQL query.
     *
     * @param SearchQuery $searchQuery
     * @return SearchResult[]
     */
    public function search(SearchQuery $searchQuery): array
    {
        $databaseType = DatabaseType::detectDatabase($this->configurationManager);
        $searchResultTypes = $this->searchResultTypesRegistry->getConfiguredSearchResultTypes();

        return $this->internalSearch($databaseType, $searchQuery, $searchResultTypes);
    }

    /**
     * Searches all sources from the registered search result types in one single SQL query.
     * Also enriches the search results with their respective search result document URLs.
     *
     * @param SearchQuery $searchQuery
     * @return SearchResultFrontend[]
     */
    public function searchFrontend(SearchQuery $searchQuery): array
    {
        $databaseType = DatabaseType::detectDatabase($this->configurationManager);
        $searchResultTypes = $this->searchResultTypesRegistry->getConfiguredSearchResultTypes();

        $results = $this->internalSearch($databaseType, $searchQuery, $searchResultTypes);

        return array_map(function (SearchResult $searchResult) use ($searchResultTypes) {
            $responsibleSearchResultType = $searchResultTypes[$searchResult->getResultTypeName()->getName()];
            $resultPageUrl = $responsibleSearchResultType->buildUrlToResultPage($searchResult);
            return $searchResult->withDocumentUrl($resultPageUrl);
        }, $results);
    }

    /**
     * @param DatabaseType $databaseType
     * @param SearchQuery $searchQuery
     * @param SearchResultTypeInterface[] $searchResultTypes
     * @return SearchResult[]
     */
    private function internalSearch(DatabaseType $databaseType, SearchQuery $searchQuery, array $searchResultTypes): array
    {
        // search query
        $searchQueryProviders = [];
        foreach ($searchResultTypes as $searchResultTypeName => $searchResultType) {
            $searchQueryProviders[$searchResultTypeName] = $searchResultType->getSearchQueryProvider($databaseType);
        }
        $searchQuerySql = $this->buildSearchQuerySql($databaseType, $searchQueryProviders);

        // prepare search term parameter from user input
        $searchTermParameterValue = self::prepareSearchTermParameterValue($databaseType, $searchQuery->getQuery());

        // prepare query
        $resultSetMapping = self::buildResultSetMapping();
        $doctrineQuery = $this->entityManager->createNativeQuery($searchQuerySql, $resultSetMapping);
        // default parameters
        $defaultParameters = [
            SearchResult::SQL_QUERY_PARAM_QUERY => $searchTermParameterValue,
            SearchResult::SQL_QUERY_PARAM_LIMIT => $searchQuery->getLimit(),
            SearchResult::SQL_QUERY_PARAM_NOW_TIME => $this->currentDateTimeProvider->getCurrentDateTime()->getTimestamp()
        ];
        $doctrineQuery->setParameters($defaultParameters);
        // search type specific additional parameters
        $additionalParameters = $this->getSearchTypeSpecificAdditionalParameters(
            array_keys($defaultParameters),
            $searchQueryProviders,
            $searchQuery
        );
        foreach ($additionalParameters as $parameterName => $additionalParameter) {
            $doctrineQuery->setParameter(
                $parameterName,
                $additionalParameter->getParameterValue(),
                $additionalParameter->getParameterDefinition()->getParameterType()
            );
        }

        // fire query
        return $doctrineQuery->getResult();
    }

    /**
     * @param DatabaseType $databaseType
     * @param SearchQueryProviderInterface[] $searchQueryProviders
     * @return string
     */
    private function buildSearchQuerySql(DatabaseType $databaseType, array $searchQueryProviders): string
    {
        $searchingQueryParts = array_map(function (SearchQueryProviderInterface $provider) {
            return $provider->getResultSearchingQueryPart();
        }, $searchQueryProviders);

        $mergingQueryParts = array_map(function (SearchQueryProviderInterface $provider) {
            return $provider->getResultMergingQueryPart();
        }, $searchQueryProviders);

        return match ($databaseType) {
            DatabaseType::MYSQL => MySQLSearchQueryBuilder::searchQuery($searchingQueryParts, $mergingQueryParts),
            DatabaseType::POSTGRES => throw new UnsupportedDatabaseException('Postgres will be supported soon <3', 1689933374),
            default => throw new UnsupportedDatabaseException(
                "Search service does not support database of type '$databaseType->name'",
                1689933081
            )
        };
    }

    private static function prepareSearchTermParameterValue(DatabaseType $databaseType, string $userInput): string
    {
        return match ($databaseType) {
            DatabaseType::MYSQL => MySQLSearchQueryBuilder::prepareSearchTermQueryParameter($userInput),
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
        $rsm->addScalarResult('meta_data', 5);
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
        $rsm->newObjectMappings['meta_data'] = [
            'className' => SearchResult::class,
            'objIndex' => 0,
            'argIndex' => 4,
        ];
        return $rsm;
    }

    /**
     * @param string[] $defaultParameterNames
     * @param SearchQueryProviderInterface[] $searchQueryProviders
     * @param SearchQuery $searchQuery
     * @return AdditionalQueryParameterValue[]
     */
    private function getSearchTypeSpecificAdditionalParameters(array $defaultParameterNames, array $searchQueryProviders, SearchQuery $searchQuery): array
    {
        /** @var AdditionalQueryParameterValue[] $additionalParameters */
        $additionalParameters = [];
        $additionalParameterValues = $searchQuery->getSearchTypeSpecificAdditionalParameters() ?: [];

        foreach ($searchQueryProviders as $searchResultTypeName => $searchQueryProvider) {
            $additionalQueryParametersDefinitions = $searchQueryProvider->getAdditionalQueryParameters();
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
