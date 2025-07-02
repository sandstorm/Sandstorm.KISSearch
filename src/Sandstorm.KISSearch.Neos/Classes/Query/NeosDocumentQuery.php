<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Query;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\DBAbstraction\MySQLHelper;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;
use Sandstorm\KISSearch\Api\Query\QueryParameters;
use Sandstorm\KISSearch\Api\Query\ResultFilterInterface;
use Sandstorm\KISSearch\Api\Query\TypeAggregatorInterface;
use Sandstorm\KISSearch\Neos\NeosContentSearchResultType;

#[Proxy(false)]
class NeosDocumentQuery implements ResultFilterInterface, TypeAggregatorInterface
{

    private const DEFAULT_SCORE_FORMULAR = '(20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor)';
    private const DEFAULT_SCORE_AGGREGATOR = 'max(r.score)';

    // ---- ResultProviderInterface

    function getFilterQueryPart(
        DatabaseType $databaseType,
        string $resultFilterIdentifier,
        string $resultTypeName,
        array $queryOptions,
        array $filterOptions
    ): string {
        // TODO postgres
        return self::getMySQLFilterQueryPart($resultFilterIdentifier, $resultTypeName, $queryOptions, $filterOptions);
    }

    function getQueryParametersForFilter(DatabaseType $databaseType, string $resultFilterIdentifier): QueryParameters
    {
        // TODO postgres

        return NeosQueryParameters::create($resultFilterIdentifier);
    }

    public static function getMySQLFilterQueryPart(
        string $resultFilterIdentifier,
        string $resultTypeName,
        array $queryOptions,
        array $filterOptions
    ): string {
        $contentRepositoryId = NeosContentSearchResultType::getContentRepositoryIdFromOptions($filterOptions, $queryOptions);
        $cteAlias = NeosContentSource::buildCTEName($contentRepositoryId);

        // priority:
        //  1. filter option
        //  2. query option
        //  3. default value
        $scoreSelector = $filterOptions[NeosContentSearchResultType::OPTION_SCORE_FORMULAR_MARIADB] ??
            $queryOptions[NeosContentSearchResultType::OPTION_SCORE_FORMULAR_MARIADB] ??
            self::DEFAULT_SCORE_FORMULAR;

        // TODO implement timed hidden when package is installed
        // now time is a global parameter
        //$queryParamNowTime = self::PARAM_NAME_NOW_TIME;
        // filter parameters
        $paramNameSiteNodeName = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            NeosQueryParameters::PARAM_NAME_SITE_NODE_NAME
        );
        $paramNameExcludeSiteNodeName = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            NeosQueryParameters::PARAM_NAME_EXCLUDE_SITE_NODE_NAME
        );
        $paramNameDimensionValues = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            NeosQueryParameters::PARAM_NAME_DIMENSION_VALUES
        );
        $paramNameWorkspace = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            NeosQueryParameters::PARAM_NAME_WORKSPACE
        );
        $paramNameRootNode = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            NeosQueryParameters::PARAM_NAME_ROOT_NODE
        );
        $paramNameContentNodeTypes = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            NeosQueryParameters::PARAM_NAME_CONTENT_NODE_TYPES
        );
        $paramNameDocumentNodeTypes = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            NeosQueryParameters::PARAM_NAME_DOCUMENT_NODE_TYPES
        );
        $paramNameInheritedContentNodeType = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            NeosQueryParameters::PARAM_NAME_INHERITED_CONTENT_NODE_TYPE
        );
        $paramNameInheritedDocumentNodeType = SearchQuery::buildFilterSpecificParameterName(
            $resultFilterIdentifier,
            NeosQueryParameters::PARAM_NAME_INHERITED_DOCUMENT_NODE_TYPE
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
                    'contentRepositoryId', '$contentRepositoryId',
                    'nodeType', nd.nodetype,
                    'dimensionsHash', nd.dimensionshash,
                    'dimensionValues', nd.dimensionvalues,
                    'origindimensionsHash', nd.origindimensionshash,
                    'origindimensionValues', nd.origindimensionvalues,
                    'contentstreamid', nd.contentstreamid,
                    'workspace', nd.workspace_name
                ) as meta_data,
                -- additional data for later meta data
                s.primarydomain as primarydomain,
                nd.document_id as document_id,
                nd.document_nodetype as document_nodetype,
                nd.document_nodename as document_nodename,
                nd.site_nodename as site_nodename,
                nd.dimensionshash as dimensionshash,
                nd.dimensionvalues as dimensionvalues,
                nd.origindimensionshash as origindimensionshash,
                nd.origindimensionvalues as origindimensionvalues,
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
        array $queryOptions,
        array $aggregatorOptions
    ): string {
        $contentRepositoryId = NeosContentSearchResultType::getContentRepositoryIdFromOptions($aggregatorOptions, $queryOptions);
        $scoreAggregator =
            $queryOptions[NeosContentSearchResultType::OPTION_SCORE_AGGREGATOR_MARIADB] ??
            $aggregatorOptions[NeosContentSearchResultType::OPTION_SCORE_AGGREGATOR_MARIADB] ??
            self::DEFAULT_SCORE_AGGREGATOR;

        // TODO postgres
        return MySQLHelper::buildDefaultResultTypeAggregator(
            $resultTypeName,
            $mergingQueryParts,
            $scoreAggregator,
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
                    'nodeName', r.document_nodetype,
                    'siteNodeName', r.site_nodename,
                    'documentNodeName', r.document_nodename,
                    'documentAggregateId', r.document_id,
                    'dimensionsHash', r.dimensionshash,
                    'dimensionValues', r.dimensionvalues,
                    'originDimensionsHash', r.origindimensionshash,
                    'originDimensionValues', r.origindimensionvalues,
                    'contentstreamid', r.contentstreamid,
                    'workspace', r.workspace_name,
                    'contentRepositoryId', '$contentRepositoryId'
                )
        SQL
        );
    }

}