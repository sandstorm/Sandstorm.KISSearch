<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\DBAbstraction\MySQLHelper;
use Sandstorm\KISSearch\Api\Query\Model\LimitMode;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;

class QueryTool
{

    public static function createSearchQuerySQL(
        DatabaseType $databaseType,
        SearchQuery $searchQuery,
        LimitMode $limitMode
    ): string
    {
        // TODO postgres

        switch ($limitMode) {
            case LimitMode::GLOBAL_LIMIT:
                return MySQLHelper::searchQueryGlobalLimit($searchQuery);
            case LimitMode::LIMIT_PER_RESULT_TYPE:
                return MySQLHelper::searchQueryLimitPerResultType($searchQuery);
        }
    }

}