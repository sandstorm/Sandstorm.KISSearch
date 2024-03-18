<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\AdditionalQueryParameterDefinition;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\AdditionalQueryParameterDefinitions;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\DefaultResultMergingQueryPart;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\DefaultResultSearchingQueryPart;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\ResultMergingQueryParts;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\ResultSearchingQueryParts;
use Sandstorm\KISSearch\SearchResultTypes\SearchQueryProviderInterface;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;

#[Proxy(false)]
class NeosContentPostgresSearchQueryProvider implements SearchQueryProviderInterface
{

    public const CTE_ALIAS = 'neos_content_results';

    function getResultSearchingQueryParts(): ResultSearchingQueryParts
    {
        $columnNameBucketCritical = NeosContentPostgresDatabaseMigration::columnNameBucketCritical();
        $columnNameBucketMajor = NeosContentPostgresDatabaseMigration::columnNameBucketMajor();
        $columnNameBucketNormal = NeosContentPostgresDatabaseMigration::columnNameBucketNormal();
        $columnNameBucketMinor = NeosContentPostgresDatabaseMigration::columnNameBucketMinor();
        $columnNameAll = NeosContentPostgresDatabaseMigration::COLUMN_SEARCH_ALL;

        $paramNameQuery = SearchResult::SQL_QUERY_PARAM_QUERY;
        $paramNameLanguage = SearchResult::SQL_QUERY_PARAM_LANGUAGE;

        $searchQuery = "to_tsquery(:$paramNameLanguage, :$paramNameQuery)";

        return ResultSearchingQueryParts::singlePart(
            new DefaultResultSearchingQueryPart(
                self::CTE_ALIAS,
                <<<SQL
                    select n.*,
                        coalesce(ts_rank_cd(n.$columnNameBucketCritical, $searchQuery), 0) as score_bucket_critical,
                        coalesce(ts_rank_cd(n.$columnNameBucketMajor, $searchQuery), 0) as score_bucket_major,
                        coalesce(ts_rank_cd(n.$columnNameBucketNormal, $searchQuery), 0) as score_bucket_normal,
                        coalesce(ts_rank_cd(n.$columnNameBucketMinor, $searchQuery), 0) as score_bucket_minor
                    from neos_contentrepository_domain_model_nodedata n
                    where n.$columnNameAll @@ $searchQuery
                SQL
            )
        );
    }

    function getResultMergingQueryParts(): ResultMergingQueryParts
    {
        $queryParamNowTime = SearchResult::SQL_QUERY_PARAM_NOW_TIME;
        $paramNameSiteNodeName = NeosContentAdditionalParameters::SITE_NODE_NAME;
        $paramNameNodeType = NeosContentAdditionalParameters::DOCUMENT_NODE_TYPES;
        $paramNameExcludeSiteNodeName = NeosContentAdditionalParameters::EXCLUDED_SITE_NODE_NAME;
        $paramNameDimensionValues = NeosContentAdditionalParameters::DIMENSION_VALUES;
        $cteAlias = self::CTE_ALIAS;

        $scoreSelector = "(100 * n.score_bucket_critical) + (25 * n.score_bucket_major) + (5 * n.score_bucket_normal) + (n.score_bucket_minor)";

        return new ResultMergingQueryParts(
            [new DefaultResultMergingQueryPart(
                NeosContentSearchResultType::name(),
                'nd.document_id',
                'nd.document_title',
                $scoreSelector,
                <<<SQL
                    jsonb_agg(jsonb_build_object(
                        'score', $scoreSelector,
                        'nodeIdentifier', nd.identifier,
                        'nodeType', nd.nodetype
                    ))
                SQL,
                <<<SQL
                    s.primarydomain as primarydomain,
                    nd.document_nodetype as document_nodetype,
                    nd.site_nodename as site_nodename,
                    nd.dimensionshash as dimensionshash,
                    nd.dimensionvalues as dimensionvalues
                SQL,
                <<<SQL
                    -- for all nodes matching search terms, we have to find the corresponding document node
                    -- to link to the content in the search result rendering
                    from $cteAlias n
                        -- inner join filters hidden and deleted nodes
                        inner join sandstorm_kissearch_nodes_and_their_documents nd
                            on nd.persistence_object_identifier = n.persistence_object_identifier
                        inner join neos_neos_domain_model_site s
                            on s.nodename = nd.site_nodename
                    where
                        -- filter timed hidden before/after nodes
                        not sandstorm_kissearch_any_timed_hidden(nd.timed_hidden, to_timestamp(:$queryParamNowTime))
                        -- filter deactivated sites
                        and s.state = 1
                        -- additional query parameters
                        and (
                            -- site node name (optional, if null all sites are searched)
                            cast(:$paramNameSiteNodeName as jsonb) is null or cast(:$paramNameSiteNodeName as jsonb) ?? nd.site_nodename
                        )
                        and (
                            cast(:$paramNameExcludeSiteNodeName as jsonb) is null or not cast(:$paramNameExcludeSiteNodeName as jsonb) ?? nd.site_nodename
                        )
                        and (
                            -- content dimension values (optional, if null all dimensions are searched)
                            cast(:$paramNameDimensionValues as jsonb) is null
                            or sandstorm_kissearch_all_dimension_values_match(
                                    cast(:$paramNameDimensionValues as jsonb),
                                    nd.dimensionvalues
                            )
                        )
                        -- node types filter
                        and (
                            cast(:$paramNameNodeType as jsonb) is null or cast(:$paramNameNodeType as jsonb) ??| nd.super_nodetypes
                        )
                SQL)],
                <<<SQL
                    jsonb_build_object(
                        'primaryDomain', (select
                                           (case when d.scheme is not null then d.scheme || ':' else '' end) ||
                                           '//' || d.hostname ||
                                           (case when d.port is not null then ':' || d.port else '' end)
                                   from neos_neos_domain_model_domain d
                                   where d.persistence_object_identifier = s.primarydomain
                                   and d.active),
                        'documentNodeType', nd.document_nodetype,
                        'siteNodeName', nd.site_nodename,
                        'dimensionsHash', nd.dimensionshash,
                        'dimensionValues', nd.dimensionvalues
                    )
                SQL,
                'nd.document_id, nd.document_title, nd.document_nodetype, nd.site_nodename, nd.dimensionshash, nd.dimensionvalues, s.primarydomain'
        );
    }

    /**
     * @return AdditionalQueryParameterDefinitions
     */
    public function getAdditionalQueryParameters(): AdditionalQueryParameterDefinitions
    {
        return AdditionalQueryParameterDefinitions::create(
            AdditionalQueryParameterDefinition::optionalJson(NeosContentAdditionalParameters::SITE_NODE_NAME, NeosContentSearchResultType::name(), function ($value) {
                return NeosContentAdditionalParameters::nodeNameMapper($value);
            }),
            AdditionalQueryParameterDefinition::optionalJson(NeosContentAdditionalParameters::EXCLUDED_SITE_NODE_NAME, NeosContentSearchResultType::name(), function ($value) {
                return NeosContentAdditionalParameters::nodeNameMapper($value);
            }),
            AdditionalQueryParameterDefinition::optionalJson(NeosContentAdditionalParameters::DIMENSION_VALUES, NeosContentSearchResultType::name(), function ($valueAsArray) {
                return new ContentDimensionValuesFilter($valueAsArray);
            }),
            AdditionalQueryParameterDefinition::optionalJson(NeosContentAdditionalParameters::DOCUMENT_NODE_TYPES, NeosContentSearchResultType::name(), function($values) {
                return $values;
            })
        );
    }

}
