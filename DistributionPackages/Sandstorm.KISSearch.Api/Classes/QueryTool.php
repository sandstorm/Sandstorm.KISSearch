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
        $sql = self::createSearchQuerySQL($databaseType, $searchQuery);
        $mergedParameters = self::mergeWithDefaultParameters($searchQuery, $searchInput);

        return $databaseAdapter->executeSearchQuery($sql, $mergedParameters);
    }

}