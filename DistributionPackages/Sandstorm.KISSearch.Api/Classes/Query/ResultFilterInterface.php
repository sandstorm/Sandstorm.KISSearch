<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;

/**
 * <b>Core extensibility API</b>
 * <br/>
 * Allows new search result filters to be defined.
 * This should be used to add mappers and filters to search sources.
 * It creates "merging" query parts.
 *
 * The idea is, that the first query "action" a.k.a. the searching query part is separated from all
 * other filters / where conditions. In other words:
 * The search sources do the fulltext matching, the result filters do all other conditions.
 * WHY?: MariaDB / MySQL does not support combining fulltext index lookups with other index lookups.
 * WARNING: Combining fulltext matching with logical ANDs might end up executing a full table scan, what
 * affects performance drastically!
 *
 * Implement your query parameters here! See Sandstorm.KISSearch.Neos for reference implementation.
 */
interface ResultFilterInterface
{

    /**
     * @param DatabaseType $databaseType
     * @param string $resultFilterIdentifier
     * @param string $resultTypeName
     * @param array $queryOptions
     * @return string
     */
    function getFilterQueryPart(DatabaseType $databaseType, string $resultFilterIdentifier, string $resultTypeName, array $queryOptions): string;

    /**
     * @param DatabaseType $databaseType
     * @param string $resultFilterIdentifier
     * @return QueryParameters
     */
    function getQueryParametersForFilter(DatabaseType $databaseType, string $resultFilterIdentifier): QueryParameters;

}