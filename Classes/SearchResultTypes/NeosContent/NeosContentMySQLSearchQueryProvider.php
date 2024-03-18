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
class NeosContentMySQLSearchQueryProvider implements SearchQueryProviderInterface
{

    public const CTE_ALIAS = 'neos_content_results';

    function getResultSearchingQueryParts(): ResultSearchingQueryParts
    {
        $columnNameBucketCritical = NeosContentMySQLDatabaseMigration::columnNameBucketCritical();
        $columnNameBucketMajor = NeosContentMySQLDatabaseMigration::columnNameBucketMajor();
        $columnNameBucketNormal = NeosContentMySQLDatabaseMigration::columnNameBucketNormal();
        $columnNameBucketMinor = NeosContentMySQLDatabaseMigration::columnNameBucketMinor();

        $paramNameQuery = SearchResult::SQL_QUERY_PARAM_QUERY;

        return ResultSearchingQueryParts::singlePart(
            new DefaultResultSearchingQueryPart(
                self::CTE_ALIAS,
                <<<SQL
                    select *,
                        match ($columnNameBucketCritical) against ( :$paramNameQuery in boolean mode ) as score_bucket_critical,
                        match ($columnNameBucketMajor) against ( :$paramNameQuery in boolean mode ) as score_bucket_major,
                        match ($columnNameBucketNormal) against ( :$paramNameQuery in boolean mode ) as score_bucket_normal,
                        match ($columnNameBucketMinor) against ( :$paramNameQuery in boolean mode ) as score_bucket_minor
                    from neos_contentrepository_domain_model_nodedata
                    where match ($columnNameBucketCritical, $columnNameBucketMajor, $columnNameBucketNormal, $columnNameBucketMinor) against ( :$paramNameQuery in boolean mode )
                SQL
            )
        );
    }

    function getResultMergingQueryParts(): ResultMergingQueryParts
    {
        $queryParamNowTime = SearchResult::SQL_QUERY_PARAM_NOW_TIME;
        $paramNameSiteNodeName = NeosContentAdditionalParameters::SITE_NODE_NAME;
        $paramNameExcludeSiteNodeName = NeosContentAdditionalParameters::EXCLUDED_SITE_NODE_NAME;
        $paramNameDimensionValues = NeosContentAdditionalParameters::DIMENSION_VALUES;
        $cteAlias = self::CTE_ALIAS;

        $scoreSelector = '(20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor)';

        return new ResultMergingQueryParts(
            [new DefaultResultMergingQueryPart(
                NeosContentSearchResultType::name(),
                'nd.document_id',
                'nd.document_title',
                $scoreSelector,
                <<<SQL
                    json_object(
                        'score', $scoreSelector,
                        'nodeIdentifier', nd.identifier,
                        'nodeType', nd.nodetype
                    )
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
                        not sandstorm_kissearch_any_timed_hidden(nd.timed_hidden, from_unixtime(:$queryParamNowTime))
                        -- filter deactivated sites
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
                SQL
            )],
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
                    'dimensionValues', r.dimensionvalues
                )
            SQL,
            null
        );
    }

    /**
     * @return AdditionalQueryParameterDefinitions
     */
    public function getAdditionalQueryParameters(): AdditionalQueryParameterDefinitions
    {
        return AdditionalQueryParameterDefinitions::create(
            AdditionalQueryParameterDefinition::optional(NeosContentAdditionalParameters::SITE_NODE_NAME, AdditionalQueryParameterDefinition::TYPE_STRING_ARRAY, NeosContentSearchResultType::name(), function ($value) {
                return NeosContentAdditionalParameters::nodeNameMapper($value);
            }),
            AdditionalQueryParameterDefinition::optional(NeosContentAdditionalParameters::EXCLUDED_SITE_NODE_NAME, AdditionalQueryParameterDefinition::TYPE_STRING_ARRAY, NeosContentSearchResultType::name(), function ($value) {
                return NeosContentAdditionalParameters::nodeNameMapper($value);
            }),
            AdditionalQueryParameterDefinition::optionalJson(NeosContentAdditionalParameters::DIMENSION_VALUES, NeosContentSearchResultType::name(), function ($valueAsArray) {
                return new ContentDimensionValuesFilter($valueAsArray);
            })
        );
    }

}
