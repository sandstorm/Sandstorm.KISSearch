<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Query;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;
use Sandstorm\KISSearch\Api\Query\SearchSourceInterface;
use Sandstorm\KISSearch\Neos\NeosContentSearchResultType;

class NeosContentSource implements SearchSourceInterface
{

    public const CTE_SOURCE = 'source__neos_cr';

    /**
     * @param string $contentRepositoryId
     * @return string
     */
    public static function buildCTEName(string $contentRepositoryId): string
    {
        return sprintf('%s__%s', self::CTE_SOURCE, $contentRepositoryId);
    }

    function getSearchingQueryPart(DatabaseType $databaseType, string $sourceIdentifier, array $queryOptions): string
    {
        $contentRepositoryId = NeosContentSearchResultType::getContentRepositoryIdFromQueryOptions($sourceIdentifier, $queryOptions);

        $columnNameBucketCritical = NeosContentSearchResultType::BUCKET_COLUMN_CRITICAL;
        $columnNameBucketMajor = NeosContentSearchResultType::BUCKET_COLUMN_MAJOR;
        $columnNameBucketNormal = NeosContentSearchResultType::BUCKET_COLUMN_NORMAL;
        $columnNameBucketMinor = NeosContentSearchResultType::BUCKET_COLUMN_MINOR;

        $paramNameQuery = SearchQuery::SQL_QUERY_PARAM_QUERY;
        $cteName = self::buildCTEName($contentRepositoryId);

        $tableName = NeosContentSearchResultType::buildCRTableName_nodes($contentRepositoryId);

        // TODO postgres

        // this is the MySQL/MariaDB variant
        return <<<SQL
            $cteName as
                (select n.*,
                    match ($columnNameBucketCritical) against ( :$paramNameQuery in boolean mode ) as score_bucket_critical,
                    match ($columnNameBucketMajor) against ( :$paramNameQuery in boolean mode ) as score_bucket_major,
                    match ($columnNameBucketNormal) against ( :$paramNameQuery in boolean mode ) as score_bucket_normal,
                    match ($columnNameBucketMinor) against ( :$paramNameQuery in boolean mode ) as score_bucket_minor
                from $tableName n
                where match ($columnNameBucketCritical, $columnNameBucketMajor, $columnNameBucketNormal, $columnNameBucketMinor) against ( :$paramNameQuery in boolean mode ))
            SQL;
    }
}