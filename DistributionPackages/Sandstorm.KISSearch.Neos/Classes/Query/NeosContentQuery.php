<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Query;

use Neos\Flow\Annotations\InjectConfiguration;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\DBAbstraction\MySQLHelper;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;
use Sandstorm\KISSearch\Api\Query\TypeAggregatorInterface;
use Sandstorm\KISSearch\Api\Query\ResultFilterInterface;
use Sandstorm\KISSearch\Api\Query\SearchSourceInterface;
use Sandstorm\KISSearch\Neos\NeosContentSearchResultType;

#[Scope('singleton')]
class NeosContentQuery implements SearchSourceInterface, ResultFilterInterface, TypeAggregatorInterface
{

    public const CTE_SOURCE = 'source__neos_content';

    public const PARAM_NAME_SITE_NODE_NAME = 'site_node';
    public const PARAM_NAME_EXCLUDE_SITE_NODE_NAME = 'exclude_site_node';
    public const PARAM_NAME_DIMENSION_VALUES = 'dimension_values';

    #[InjectConfiguration(path: 'Neos.contentRepositoryId', package: 'Sandstorm.KISSearch')]
    protected string $contentRepositoryId;


    public static function createInstance(string $contentRepositoryId): self
    {
        $instance = new NeosContentQuery();
        $instance->contentRepositoryId = $contentRepositoryId;
        return $instance;
    }

    // ---- SearchSourceInterface

    function getSearchingQueryPart(DatabaseType $databaseType): string
    {
        $columnNameBucketCritical = NeosContentSearchResultType::BUCKET_COLUMN_CRITICAL;
        $columnNameBucketMajor = NeosContentSearchResultType::BUCKET_COLUMN_MAJOR;
        $columnNameBucketNormal = NeosContentSearchResultType::BUCKET_COLUMN_NORMAL;
        $columnNameBucketMinor = NeosContentSearchResultType::BUCKET_COLUMN_MINOR;

        $paramNameQuery = SearchQuery::SQL_QUERY_PARAM_QUERY;
        $cteName = self::CTE_SOURCE;

        $tableName = NeosContentSearchResultType::buildCRTableName_nodes($this->contentRepositoryId);

        // TODO database type

        // this is the MySQL/MariaDB variant
        return <<<SQL
            $cteName as
                (select n.*,
                    match ($columnNameBucketCritical) against ( :$paramNameQuery in boolean mode ) as score_bucket_critical,
                    match ($columnNameBucketMajor) against ( :$paramNameQuery in boolean mode ) as score_bucket_major,
                    match ($columnNameBucketNormal) against ( :$paramNameQuery in boolean mode ) as score_bucket_normal,
                    match ($columnNameBucketMinor) against ( :$paramNameQuery in boolean mode ) as score_bucket_minor
                from $tableName n
                where match (n.$columnNameBucketCritical, n.$columnNameBucketMajor, n.$columnNameBucketNormal, n.$columnNameBucketMinor) against ( :$paramNameQuery in boolean mode ))
            SQL;
    }

    // ---- ResultProviderInterface

    function getFilterQueryPart(DatabaseType $databaseType, string $resultFilterIdentifier, string $resultTypeName): string
    {
        // TODO postgres
        return self::getMySQLFilterQueryPart($resultFilterIdentifier, $resultTypeName);
    }

    public static function getMySQLFilterQueryPart(string $resultFilterIdentifier, string $resultTypeName): string
    {
        $cteAlias = self::CTE_SOURCE;
        $scoreSelector = '(20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor)';

        // filter parameters
        // now time is a global parameter
        // TODO implement timed hidden when package is installed
        //$queryParamNowTime = self::PARAM_NAME_NOW_TIME;
        $paramNameSiteNodeName = SearchQuery::buildFilterSpecificParameterName($resultFilterIdentifier, self::PARAM_NAME_SITE_NODE_NAME);
        $paramNameExcludeSiteNodeName = SearchQuery::buildFilterSpecificParameterName($resultFilterIdentifier, self::PARAM_NAME_EXCLUDE_SITE_NODE_NAME);
        $paramNameDimensionValues = SearchQuery::buildFilterSpecificParameterName($resultFilterIdentifier, self::PARAM_NAME_DIMENSION_VALUES);

        return <<<SQL
            select
                -- KISSearch API
                '$resultTypeName' as result_type,
                nd.document_id as result_id,
                nd.document_title as result_title,
                nd.document_uri_path as result_url,
                $scoreSelector as score,
                json_object(
                    'score', $scoreSelector,
                    'nodeIdentifier', nd.identifier,
                    'nodeType', nd.nodetype
                ) as meta_data,
                -- additional data for later aggregate meta data
                s.primarydomain as primarydomain,
                nd.document_nodetype as document_nodetype,
                nd.site_nodename as site_nodename,
                nd.dimensionshash as dimensionshash,
                nd.dimensionvalues as dimensionvalues
            -- for all nodes matching search terms, we have to find the corresponding document node
            -- to link to the content in the search result rendering
            from $cteAlias n
                -- inner join filters hidden and deleted nodes
                inner join sandstorm_kissearch_nodes_and_their_documents nd
                    on nd.relationanchorpoint = n.relationanchorpoint
                inner join neos_neos_domain_model_site s
                    on s.nodename = nd.site_nodename
            where
                -- filter deactivated sites TODO
                and s.state = 1
                -- additional query parameters
                and (
                    -- site node name (optional, if null all sites are searched)
                    json_value(json_array(:$paramNameSiteNodeName), '$[0]') is null or nd.site_nodename in (:$paramNameSiteNodeName)
                )
                and (
                    json_value(json_array(:$paramNameExcludeSiteNodeName), '$[0]') is null or nd.site_nodename not in (:$paramNameExcludeSiteNodeName)
                )
                and (
                    -- content dimension values (optional, if null all dimensions are searched)
                    :$paramNameDimensionValues is null
                    or sandstorm_kissearch_all_dimension_values_match(
                            :$paramNameDimensionValues,
                            nd.dimensionvalues
                    )
                )
        SQL;
    }

    // ---- ResultAggregatorInterface

    function getResultTypeAggregatorQueryPart(
        DatabaseType $databaseType,
        string $resultTypeName,
        array $mergingQueryParts
    ): string {

        // TODO postgres
        return MySQLHelper::buildDefaultResultTypeAggregator($resultTypeName, $mergingQueryParts, <<<SQL
            json_object(
                    'primaryDomain', (select
                                   concat(
                                       if(d.scheme is not null, concat(d.scheme, ':'), ''),
                                       '//', d.hostname,
                                       if(d.port is not null, concat(':', d.port), '')
                                   )
                                      -- TODO new domain projection
                               from neos_neos_domain_model_domain d
                               where d.persistence_object_identifier = r.primarydomain
                               and d.active = 1),
                    'documentNodeType', r.document_nodetype,
                    'siteNodeName', r.site_nodename,
                    'dimensionsHash', r.dimensionshash,
                    'dimensionValues', r.dimensionvalues
                )
        SQL);
    }

}