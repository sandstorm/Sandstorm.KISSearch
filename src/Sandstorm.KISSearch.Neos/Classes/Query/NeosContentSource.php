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

    function getSearchingQueryPart(
        DatabaseType $databaseType,
        string $sourceIdentifier,
        array $queryOptions,
        array $filterOptions
    ): string
    {
        $contentRepositoryId = NeosContentSearchResultType::getContentRepositoryIdFromOptions($filterOptions, $queryOptions);

        $columnNameBucketCritical = NeosContentSearchResultType::BUCKET_COLUMN_CRITICAL;
        $columnNameBucketMajor = NeosContentSearchResultType::BUCKET_COLUMN_MAJOR;
        $columnNameBucketNormal = NeosContentSearchResultType::BUCKET_COLUMN_NORMAL;
        $columnNameBucketMinor = NeosContentSearchResultType::BUCKET_COLUMN_MINOR;

        $paramNameQuery = SearchQuery::SQL_QUERY_PARAM_QUERY;
        $cteName = self::buildCTEName($contentRepositoryId);

        $nodesTableName = NeosContentSearchResultType::buildCRTableName_nodes($contentRepositoryId);
        $bucketsTableName = NeosContentSearchResultType::buildSearchBucketsTableName($contentRepositoryId);

        // TODO postgres

        // this is the MySQL/MariaDB variant
        return <<<SQL
            $cteName as
                (select n.*,
                    match (sb.$columnNameBucketCritical) against ( :$paramNameQuery in boolean mode ) as score_bucket_critical,
                    match (sb.$columnNameBucketMajor) against ( :$paramNameQuery in boolean mode ) as score_bucket_major,
                    match (sb.$columnNameBucketNormal) against ( :$paramNameQuery in boolean mode ) as score_bucket_normal,
                    match (sb.$columnNameBucketMinor) against ( :$paramNameQuery in boolean mode ) as score_bucket_minor
                from $nodesTableName n
                inner join $bucketsTableName sb on sb.relationanchorpoint = n.relationanchorpoint
                where match (sb.$columnNameBucketCritical, sb.$columnNameBucketMajor, sb.$columnNameBucketNormal, sb.$columnNameBucketMinor) against ( :$paramNameQuery in boolean mode ))
            SQL;
    }

    public function getCTEName(
        DatabaseType $databaseType,
        string $sourceIdentifier,
        array $queryOptions,
        array $filterOptions
    ): string {
        $contentRepositoryId = NeosContentSearchResultType::getContentRepositoryIdFromOptions($filterOptions, $queryOptions);
        return self::buildCTEName($contentRepositoryId);
    }

}