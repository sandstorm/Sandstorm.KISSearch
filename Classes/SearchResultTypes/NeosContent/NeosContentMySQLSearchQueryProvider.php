<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\AdditionalQueryParameterDefinition;
use Sandstorm\KISSearch\SearchResultTypes\AdditionalQueryParameterValue;
use Sandstorm\KISSearch\SearchResultTypes\SearchQueryProviderInterface;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;

#[Proxy(false)]
class NeosContentMySQLSearchQueryProvider implements SearchQueryProviderInterface
{

    public const ADDITIONAL_QUERY_PARAM_NAME_SITE_NODE_NAME = 'neosContentSiteNodeName';

    function getResultSearchingQueryPart(): string
    {
        $columnNameBucketCritical = NeosContentMySQLDatabaseMigration::columnNameBucketCritical();
        $columnNameBucketMajor = NeosContentMySQLDatabaseMigration::columnNameBucketMajor();
        $columnNameBucketNormal = NeosContentMySQLDatabaseMigration::columnNameBucketNormal();
        $columnNameBucketMinor = NeosContentMySQLDatabaseMigration::columnNameBucketMinor();

        $paramNameQuery = SearchResult::SQL_QUERY_PARAM_QUERY;

        return <<<SQL
             neos_content_results as (select *,
                                             match ($columnNameBucketCritical) against ( :$paramNameQuery IN BOOLEAN MODE ) as score_bucket_critical,
                                             match ($columnNameBucketMajor) against ( :$paramNameQuery IN BOOLEAN MODE ) as score_bucket_major,
                                             match ($columnNameBucketNormal) against ( :$paramNameQuery IN BOOLEAN MODE ) as score_bucket_normal,
                                             match ($columnNameBucketMinor) against ( :$paramNameQuery IN BOOLEAN MODE ) as score_bucket_minor
                                      from neos_contentrepository_domain_model_nodedata
                                      where match ($columnNameBucketCritical, $columnNameBucketMajor, $columnNameBucketNormal, $columnNameBucketMinor) against ( :$paramNameQuery in boolean mode )
             )
        SQL;
    }

    function getResultMergingQueryPart(): string
    {
        $resultTypeName = NeosContentSearchResultType::TYPE_NAME;
        $queryParamNowTime = SearchResult::SQL_QUERY_PARAM_NOW_TIME;
        $paramNameSiteNodeName = self::ADDITIONAL_QUERY_PARAM_NAME_SITE_NODE_NAME;

        return <<<SQL
             -- add neos_page results to our result union
             select nd.document_id as result_id,
                    nd.document_title as result_title,
                    '$resultTypeName' as result_type,
                    (20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor) as score,
                    json_object(
                        'nodeIdentifier', nd.identifier,
                        'nodeType', nd.nodetype,
                        'documentNodeType', nd.document_nodetype,
                        'siteNodeName', nd.site_nodename,
                        'dimensionsHash', nd.dimensionshash,
                        'primaryDomain', (select
                                       concat(
                                           if(d.scheme is not null, concat(d.scheme, ':'), ''),
                                           '//', d.hostname,
                                           if(d.port is not null, concat(':', d.port), '')
                                       )
                                   from neos_neos_domain_model_domain d
                                   where d.persistence_object_identifier = s.primarydomain
                                   and d.active = 1)
                    ) as meta_data
             -- for all nodes matching search terms, we have to find the corresponding document node
             -- to link to the content in the search result rendering
             from neos_content_results n
                 -- inner join filters hidden and deleted nodes
                 inner join sandstorm_kissearch_nodes_and_their_documents nd
                     on nd.identifier = n.identifier
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
                     :$paramNameSiteNodeName is null or nd.site_nodename = :$paramNameSiteNodeName
                 )
        SQL;
    }

    /**
     * @return AdditionalQueryParameterValue[]
     */
    public function getAdditionalQueryParameters(): array
    {
        return [
            AdditionalQueryParameterDefinition::optional(self::ADDITIONAL_QUERY_PARAM_NAME_SITE_NODE_NAME, 'string', NeosContentSearchResultType::name())
        ];
    }

}
