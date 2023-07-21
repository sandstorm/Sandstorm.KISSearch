<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\SearchQueryProviderInterface;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;

#[Proxy(false)]
class NeosContentMySQLSearchQueryProvider implements SearchQueryProviderInterface
{

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
        $resultTypeName = NeosContentSearchResultType::$TYPE_NAME;

        return <<<SQL

            # add neos_page results to our result union
             select nd.document_id as result_id,
                    nd.document_title as result_title,
                    '$resultTypeName' as result_type,
                    (20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor) as score

             # for all nodes matching search terms, we have to find the corresponding document node
             # to link to the content in the search result rendering
             from neos_content_results n
                 inner join sandstorm_kissearch_nodes_and_their_documents nd
                    on nd.identifier = n.identifier
        SQL;
    }

}
