<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Query;

use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\DBAbstraction\MySQLHelper;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;
use Sandstorm\KISSearch\Api\Query\QueryParameterMapper;
use Sandstorm\KISSearch\Api\Query\ResultFilterInterface;
use Sandstorm\KISSearch\Api\Query\SearchSourceInterface;
use Sandstorm\KISSearch\Api\Query\TypeAggregatorInterface;
use Sandstorm\KISSearch\Flow\InvalidConfigurationException;
use Sandstorm\KISSearch\Neos\NeosContentSearchResultType;

#[Proxy(false)]
class NeosContentQuery implements SearchSourceInterface, ResultFilterInterface, TypeAggregatorInterface
{

    private const OPTION_CONTENT_REPOSITORY = 'contentRepository';

    public const CTE_SOURCE = 'source__neos_cr';

    public const PARAM_NAME_WORKSPACE = 'workspace';
    public const PARAM_NAME_SITE_NODE_NAME = 'site_node';
    public const PARAM_NAME_EXCLUDE_SITE_NODE_NAME = 'exclude_site_node';
    public const PARAM_NAME_DIMENSION_VALUES = 'dimension_values';
    public const PARAM_NAME_CONTENT_NODE_TYPES = 'content_node_types';
    public const PARAM_NAME_DOCUMENT_NODE_TYPES = 'document_node_types';
    public const PARAM_NAME_ROOT_NODE = 'root_node';
    public const PARAM_NAME_INHERITED_CONTENT_NODE_TYPE = 'inherited_content_node_type';
    public const PARAM_NAME_INHERITED_DOCUMENT_NODE_TYPE = 'inherited_document_node_type';

    private static function getContentRepositoryIdFromQueryOptions(string $identifier, array $queryOptions): string
    {
        $contentRepositoryId = $queryOptions[self::OPTION_CONTENT_REPOSITORY] ??
            throw new InvalidConfigurationException(sprintf("No '%s' option set in query options.", self::OPTION_CONTENT_REPOSITORY));
        if (!is_string($contentRepositoryId) || strlen(trim($contentRepositoryId)) === 0) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid query options '%s' found in %s; value must be a non-empty string but was: %s",
                    self::OPTION_CONTENT_REPOSITORY,
                    $identifier,
                    gettype($contentRepositoryId)
                )
            );
        }
        return $contentRepositoryId;
    }

    /**
     * @param string $contentRepositoryId
     * @return string
     */
    private static function buildCTEName(string $contentRepositoryId): string
    {
        return sprintf('%s__%s', self::CTE_SOURCE, $contentRepositoryId);
    }

    // ---- SearchSourceInterface

    function getSearchingQueryPart(DatabaseType $databaseType, string $sourceIdentifier, array $queryOptions): string
    {
        $contentRepositoryId = self::getContentRepositoryIdFromQueryOptions($sourceIdentifier, $queryOptions);

        $columnNameBucketCritical = NeosContentSearchResultType::BUCKET_COLUMN_CRITICAL;
        $columnNameBucketMajor = NeosContentSearchResultType::BUCKET_COLUMN_MAJOR;
        $columnNameBucketNormal = NeosContentSearchResultType::BUCKET_COLUMN_NORMAL;
        $columnNameBucketMinor = NeosContentSearchResultType::BUCKET_COLUMN_MINOR;

        $paramNameQuery = SearchQuery::SQL_QUERY_PARAM_QUERY;
        $cteName = self::buildCTEName($contentRepositoryId);

        $tableName = NeosContentSearchResultType::buildCRTableName_nodes($contentRepositoryId);

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
                where match ($columnNameBucketCritical, $columnNameBucketMajor, $columnNameBucketNormal, $columnNameBucketMinor) against ( :$paramNameQuery in boolean mode ))
            SQL;
    }

    // ---- ResultProviderInterface

    function getFilterQueryPart(
        DatabaseType $databaseType,
        string $resultFilterIdentifier,
        string $resultTypeName,
        array $queryOptions
    ): string {
        // TODO postgres
        return self::getMySQLFilterQueryPart($resultFilterIdentifier, $resultTypeName, $queryOptions);
    }

    function getQueryParameterMapper(DatabaseType $databaseType, string $resultFilterIdentifier): QueryParameterMapper
    {
        // TODO postgres

        return (new QueryParameterMapper())
            ->addFilterSpecificMapper($resultFilterIdentifier, self::PARAM_NAME_SITE_NODE_NAME, function ($rawValue) {
                return self::nodeNameMapper($rawValue);
            })
            ->addFilterSpecificMapper(
                $resultFilterIdentifier,
                self::PARAM_NAME_EXCLUDE_SITE_NODE_NAME,
                function ($rawValue) {
                    return self::nodeNameMapper($rawValue);
                }
            )
            ->addFilterSpecificMapper($resultFilterIdentifier, self::PARAM_NAME_DIMENSION_VALUES, function ($rawValue) {
                return self::dimensionValuesMapper($rawValue);
            })
            ->addFilterSpecificMapper($resultFilterIdentifier, self::PARAM_NAME_WORKSPACE, function ($rawValue) {
                return $rawValue;
            })
            ->addFilterSpecificMapper($resultFilterIdentifier, self::PARAM_NAME_ROOT_NODE, function ($rawValue) {
                return $rawValue;
            })
            ->addFilterSpecificMapper(
                $resultFilterIdentifier,
                self::PARAM_NAME_CONTENT_NODE_TYPES,
                function ($rawValue) {
                    return self::nodeTypeNameMapper($rawValue);
                }
            )
            ->addFilterSpecificMapper(
                $resultFilterIdentifier,
                self::PARAM_NAME_DOCUMENT_NODE_TYPES,
                function ($rawValue) {
                    return self::nodeTypeNameMapper($rawValue);
                }
            )
            ->addFilterSpecificMapper(
                $resultFilterIdentifier,
                self::PARAM_NAME_INHERITED_CONTENT_NODE_TYPE,
                function ($rawValue) {
                    return self::singleNodeTypeNameMapper($rawValue);
                }
            )
            ->addFilterSpecificMapper(
                $resultFilterIdentifier,
                self::PARAM_NAME_INHERITED_DOCUMENT_NODE_TYPE,
                function ($rawValue) {
                    return self::singleNodeTypeNameMapper($rawValue);
                }
            );
    }

    /**
     * @param string[]|string|NodeName|NodeName[] $nodeNames
     * @return string[]
     */
    private static function nodeNameMapper(string|NodeName|array $nodeNames): array
    {
        if (is_array($nodeNames)) {
            return array_map(function (mixed $nodeName) {
                if ($nodeName instanceof NodeName) {
                    return $nodeName->__toString();
                }
                return $nodeName;
            }, $nodeNames);
        }
        if ($nodeNames instanceof NodeName) {
            return [$nodeNames->__toString()];
        }
        return [(string)$nodeNames];
    }

    /**
     * @param string[]|string|NodeTypeName|NodeTypeName[] $nodeTypeNames
     * @return string[]
     */
    private static function nodeTypeNameMapper(string|NodeTypeName|array $nodeTypeNames): array
    {
        if (is_array($nodeTypeNames)) {
            return array_map(function (mixed $nodeTypeName) {
                if ($nodeTypeName instanceof NodeTypeName) {
                    return $nodeTypeName->value;
                }
                return $nodeTypeName;
            }, $nodeTypeNames);
        }
        if ($nodeTypeNames instanceof NodeTypeName) {
            return [$nodeTypeNames->value];
        }
        return [(string)$nodeTypeNames];
    }

    /**
     * @param string|NodeTypeName $nodeTypeNames
     * @return string[]
     */
    private static function singleNodeTypeNameMapper(string|NodeTypeName $nodeTypeNames): array
    {
        if ($nodeTypeNames instanceof NodeTypeName) {
            return [$nodeTypeNames->value];
        }
        return [(string)$nodeTypeNames];
    }

    private static function dimensionValuesMapper(array $input): string
    {
        $result = [];
        foreach ($input as $dimensionName => $dimensionValue) {
            $result[] = [
                'dimension_name' => $dimensionName,
                'filter_value' => $dimensionValue
            ];
        }
        return json_encode($result);
    }

    public static function getMySQLFilterQueryPart(string $resultFilterIdentifier, string $resultTypeName, array $queryOptions): string
    {
        $contentRepositoryId = self::getContentRepositoryIdFromQueryOptions($resultFilterIdentifier, $queryOptions);
        $cteAlias = self::buildCTEName($contentRepositoryId);
        $scoreSelector = '(20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor)';

        // filter parameters
        // now time is a global parameter
        // TODO implement timed hidden when package is installed
        //$queryParamNowTime = self::PARAM_NAME_NOW_TIME;
        $paramNameSiteNodeName = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            self::PARAM_NAME_SITE_NODE_NAME
        );
        $paramNameExcludeSiteNodeName = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            self::PARAM_NAME_EXCLUDE_SITE_NODE_NAME
        );
        $paramNameDimensionValues = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            self::PARAM_NAME_DIMENSION_VALUES
        );
        $paramNameWorkspace = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            self::PARAM_NAME_WORKSPACE
        );
        $paramNameRootNode = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            self::PARAM_NAME_ROOT_NODE
        );
        $paramNameContentNodeTypes = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            self::PARAM_NAME_CONTENT_NODE_TYPES
        );
        $paramNameDocumentNodeTypes = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            self::PARAM_NAME_DOCUMENT_NODE_TYPES
        );
        $paramNameInheritedContentNodeType = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            self::PARAM_NAME_INHERITED_CONTENT_NODE_TYPE
        );
        $paramNameInheritedDocumentNodeType = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            self::PARAM_NAME_INHERITED_DOCUMENT_NODE_TYPE
        );
        return <<<SQL
            select
                -- KISSearch API
                '$resultTypeName' as result_type,
                md5(concat_ws('__', nd.document_id, nd.dimensionshash, nd.contentstreamid, '$contentRepositoryId')) as result_id,
                nd.document_title as result_title,
                nd.document_uri_path as result_url,
                $scoreSelector as score,
                json_object(
                    'score', $scoreSelector,
                    'nodeIdentifier', nd.node_id,
                    'documentNodeIdentifier', nd.document_id,
                    'contentRepository', '$contentRepositoryId',
                    'nodeType', nd.nodetype,
                    'dimensionsHash', nd.dimensionshash,
                    'dimensionValues', nd.dimensionvalues,
                    'contentstreamid', nd.contentstreamid,
                    'workspace', nd.workspace_name
                ) as meta_data,
                -- additional data for later meta data
                s.primarydomain as primarydomain,
                nd.document_nodetype as document_nodetype,
                nd.site_nodename as site_nodename,
                nd.dimensionshash as dimensionshash,
                nd.dimensionvalues as dimensionvalues,
                nd.contentstreamid as contentstreamid,
                nd.workspace_name as workspace_name
            -- for all nodes matching search terms, we have to find the corresponding document node
            -- to link to the content in the search result rendering
            from $cteAlias n
                -- inner join filters hidden and deleted nodes
                inner join sandstorm_kissearch_nodes_and_their_documents_$contentRepositoryId nd
                    on nd.relationanchorpoint = n.relationanchorpoint
                inner join neos_neos_domain_model_site s
                    on s.nodename = nd.site_nodename
            where
                -- filter deactivated sites TODO
                s.state = 1
                -- additional query parameters
                and (
                    :$paramNameWorkspace is null or nd.workspace_name = :$paramNameWorkspace 
                )
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
                and (
                    :$paramNameRootNode is null or json_contains(nd.parent_documents, json_quote(:$paramNameRootNode))
                )
                and (
                    json_value(json_array(:$paramNameDocumentNodeTypes), '$[0]') is null or nd.document_nodetype in (:$paramNameDocumentNodeTypes)
                )
                and (
                    json_value(json_array(:$paramNameContentNodeTypes), '$[0]') is null or nd.nodetype in (:$paramNameContentNodeTypes)
                )
                and (
                    :$paramNameInheritedContentNodeType is null or json_contains(nd.inherited_nodetypes, json_quote(:$paramNameInheritedContentNodeType))
                )
                and (
                    :$paramNameInheritedDocumentNodeType is null or json_contains(nd.inherited_document_nodetypes, json_quote(:$paramNameInheritedDocumentNodeType))
                )
        SQL;
    }

    // ---- ResultAggregatorInterface

    function getResultTypeAggregatorQueryPart(
        DatabaseType $databaseType,
        string $resultTypeName,
        array $mergingQueryParts,
        array $queryOptions
    ): string {
        // TODO postgres
        return MySQLHelper::buildDefaultResultTypeAggregator(
            $resultTypeName,
            $mergingQueryParts,
            <<<SQL
            json_object(
                    'primaryDomain', (select
                                   concat(
                                       if(d.scheme is not null, concat(d.scheme, ':'), ''),
                                       '//', d.hostname,
                                       if(d.port is not null, concat(':', d.port), '')
                                   )
                               from neos_neos_domain_model_domain d
                               where d.persistence_object_identifier = r.primarydomain
                               and d.active = 1),
                    'documentNodeType', r.document_nodetype,
                    'siteNodeName', r.site_nodename,
                    'dimensionsHash', r.dimensionshash,
                    'dimensionValues', r.dimensionvalues,
                    'contentstreamid', r.contentstreamid,
                    'workspace', r.workspace_name
                )
        SQL
        );
    }

}