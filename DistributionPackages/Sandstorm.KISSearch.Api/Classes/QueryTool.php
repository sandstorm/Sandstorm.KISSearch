<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\DBAbstraction\MySQLHelper;
use Sandstorm\KISSearch\Api\DBAbstraction\SearchQueryDatabaseAdapterInterface;
use Sandstorm\KISSearch\Api\Query\Model\SearchInput;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;

class QueryTool
{

    public static function createSearchQuerySQL(
        DatabaseType $databaseType,
        SearchQuery $searchQuery
    ): string
    {
        // TODO postgres

        return MySQLHelper::buildSearchQuerySql($searchQuery);
    }

    public static function mergeWithDefaultParameters(SearchQuery $query, SearchInput $input): array
    {
        $mergedParameters = $query->getDefaultParameters();
        foreach ($input->getParameters() as $name => $value) {
            $mergedParameters[$name] = $value;
        }
        return $mergedParameters;
    }

    /**
     * @param DatabaseType $databaseType
     * @param SearchQuery $searchQuery
     * @param SearchInput $searchInput
     * @param SearchQueryDatabaseAdapterInterface $databaseAdapter
     * @return array<SearchResult>
     */
    public static function executeSearchQuery(
        DatabaseType $databaseType,
        SearchQuery $searchQuery,
        SearchInput $searchInput,
        SearchQueryDatabaseAdapterInterface $databaseAdapter
    ): array
    {
        $queryParameterMappers = $searchQuery->getParameterMappers();
        $sql = self::createSearchQuerySQL($databaseType, $searchQuery);
        $mergedParameters = self::mergeWithDefaultParameters($searchQuery, $searchInput);

        // call parameter mappers
        $mappedParameters = [];
        foreach ($queryParameterMappers as $parameterName => $mapper) {
            $parameterValue = $mergedParameters[$parameterName] ?? null;
            if ($mapper === null || $parameterValue === null) {
                // no mapping -> just take the raw value
                $mappedParameters[$parameterName] = $parameterValue;
            } else {
                $mappedParameters[$parameterName] = $mapper($parameterValue);
            }
        }

        // global default parameters
        $mappedParameters[SearchQuery::SQL_QUERY_PARAM_QUERY] = self::prepareSearchTermParameterValue($databaseType, $searchInput->getSearchQuery());
        $mappedParameters[SearchQuery::SQL_QUERY_PARAM_GLOBAL_LIMIT] = $searchInput->getGlobalLimit();
        foreach ($searchInput->getResultTypeLimits() as $searchResultTypeName => $limit) {
            $mappedParameters[SearchQuery::buildAggregatorLimitParameterName($searchResultTypeName)] = $limit;
        }

        return $databaseAdapter->executeSearchQuery($sql, $mappedParameters);
    }

    private static function prepareSearchTermParameterValue(
        DatabaseType $databaseType, string $userInput): string
    {
        // TODO postgres
        return match ($databaseType) {
            DatabaseType::MYSQL, DatabaseType::MARIADB => MySQLHelper::prepareSearchTermQueryParameter($userInput),
            //DatabaseType::POSTGRES => PostgresHelper::prepareSearchTermQueryParameter($userInput),
            default => throw new UnsupportedDatabaseException(
                "Search service does not support database of type '$databaseType->name'",
                1689936258
            )
        };
    }


}