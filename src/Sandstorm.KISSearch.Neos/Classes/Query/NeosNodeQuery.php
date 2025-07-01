<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Query;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\DBAbstraction\MySQLHelper;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;
use Sandstorm\KISSearch\Api\Query\QueryParameters;
use Sandstorm\KISSearch\Api\Query\ResultFilterInterface;
use Sandstorm\KISSearch\Api\Query\TypeAggregatorInterface;
use Sandstorm\KISSearch\Neos\NeosContentSearchResultType;

// prototypish for now. the only difference to NeosDocumentQuery is the result_id md5 aggregator is built out of the node_id
// instead of the document_id (we do **not** aggregate by parent document here)!

/**
 * @deprecated this is experimental -> this will change for sure
 */
class NeosNodeQuery implements ResultFilterInterface, TypeAggregatorInterface
{

    function getFilterQueryPart(
        DatabaseType $databaseType,
        string $resultFilterIdentifier,
        string $resultTypeName,
        array $queryOptions,
        array $filterOptions
    ): string {
        $contentRepositoryId = NeosContentSearchResultType::getContentRepositoryIdFromOptions($filterOptions, $queryOptions);
        $cteAlias = NeosContentSource::buildCTEName($contentRepositoryId);
        $scoreSelector = '(20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor)';

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
                md5(concat_ws('__', nd.node_id, nd.dimensionshash, nd.contentstreamid, '$contentRepositoryId')) as result_id,
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

    function getQueryParametersForFilter(DatabaseType $databaseType, string $resultFilterIdentifier): QueryParameters
    {
        return NeosQueryParameters::create($resultFilterIdentifier);
    }

    function getResultTypeAggregatorQueryPart(
        DatabaseType $databaseType,
        string $resultTypeName,
        array $mergingQueryParts,
        array $queryOptions,
        array $aggregatorOptions
    ): string {
        // TODO postgres
        return MySQLHelper::buildDefaultResultTypeAggregator(
            $resultTypeName,
            $mergingQueryParts,
            'max(r.score)',
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