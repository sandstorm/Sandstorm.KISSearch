-- searching query part
with neos_content_results as (select *,
                                     match (search_bucket_critical) against ( :query IN BOOLEAN MODE ) as score_bucket_critical,
                                     match (search_bucket_major) against ( :query IN BOOLEAN MODE )    as score_bucket_major,
                                     match (search_bucket_normal) against ( :query IN BOOLEAN MODE )   as score_bucket_normal,
                                     match (search_bucket_minor) against ( :query IN BOOLEAN MODE )    as score_bucket_minor
                              from neos_contentrepository_domain_model_nodedata
                              where match (search_bucket_critical, search_bucket_major, search_bucket_normal, search_bucket_normal) against ( :query in boolean mode )),
     all_results as (
       -- union of all search types
       -- add neos_page results to our result union
       select nd.document_id               as result_id,
              nd.document_title            as result_title,
              'neos_content'               as result_type,
              (20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) +
              (0.5 * n.score_bucket_minor) as score,
              json_object(
                'nodeIdentifier', nd.identifier,
                'nodeType', nd.nodetype,
                'documentNodeType', nd.document_nodetype,
                'siteNodeName', nd.site_nodename,
                'dimensionsHash', nd.dimensionshash
                )                          as aggregate_meta_data,
              json_object(
                'primaryDomain', (select concat(
                                           if(d.scheme is not null, concat(d.scheme, ':'), ''),
                                           '//', d.hostname,
                                           if(d.port is not null, concat(':', d.port), '')
                                           )
                                  from neos_neos_domain_model_domain d
                                  where d.persistence_object_identifier = s.primarydomain
                                    and d.active = 1)
                )                          as meta_data
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
         ))
select
  -- select all search results
  a.result_id                          as result_id,
  a.result_type                        as result_type,
  a.result_title                       as result_title,
  -- max score wins
  max(score)                           as score,
  -- meta data
  a.meta_data                          as group_meta_data,
  json_arrayagg(a.aggregate_meta_data) as aggregate_meta_data,
  count(a.result_id)                   as match_count
from all_results a
     -- group by result id and type in case multiple merging query parts return the same result
group by result_id, result_type
order by score desc
limit :$limitParamName;


with neos_content_results as (select *,
                                     match (search_bucket_critical) against ( ? in boolean mode ) as
                                       score_bucket_critical,
                                     match (search_bucket_major) against ( ? in boolean mode )    as
                                       score_bucket_major,
                                     match (search_bucket_normal) against ( ? in boolean mode )   as
                                       score_bucket_normal,
                                     match (search_bucket_minor) against ( ? in boolean mode )    as
                                       score_bucket_minor
                              from neos_contentrepository_domain_model_nodedata
                              where match (search_bucket_critical, search_bucket_major,
                                      search_bucket_normal, search_bucket_minor) against ( ? in boolean mode )),
     all_results as (
       -- union of all search types
       select nd.document_id                                            as result_id,
              nd.document_title                                         as result_title,
              'neos_content'                                            as result_type,
              (20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1
                * n.score_bucket_normal) + (0.5 * n.score_bucket_minor) as score,
              json_object(
                'score', (20 * n.score_bucket_critical) + (5 *
                                                           n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 *
                                                                                                                  n.score_bucket_minor),
                'nodeIdentifier', nd.identifier,
                'nodeType', nd.nodetype
                )                                                       as result_meta_data,
              json_object(
                'primaryDomain', (select concat(
                                           if(d.scheme is not null, concat(d.scheme,
                                                                           ':'), ''),
                                           '//', d.hostname,
                                           if(d.port is not null, concat(':', d.port),
                                              '')
                                           )
                                  from neos_neos_domain_model_domain d
                                  where d.persistence_object_identifier =
                                        s.primarydomain
                                    and d.active = 1),
                'documentNodeType', nd.document_nodetype,
                'siteNodeName', nd.site_nodename,
                'dimensionsHash', nd.dimensionshash,
                'dimensionValues', nd.dimensionvalues
                )                                                       as group_meta_data
              -- for all nodes matching search terms, we have to find the
              -- to link to the content in the search result rendering
       from neos_content_results n
              -- inner join filters hidden and deleted nodes
              inner join sandstorm_kissearch_nodes_and_their_documents nd
                         on nd.persistence_object_identifier =
                            n.persistence_object_identifier
              inner join neos_neos_domain_model_site s
                         on s.nodename = nd.site_nodename
       where
         -- filter timed hidden before/after nodes
         not sandstorm_kissearch_any_timed_hidden(nd.timed_hidden,
                                                  from_unixtime(?))
         -- filter deactivated sites
         and s.state = 1
         -- additional query parameters
         and (
         -- site node name (optional, if null all sites are searched)
           json_value(json_array(?), '$[0]') is null or nd.site_nodename
           in (?)
         )
         and (
           json_value(json_array(?), '$[0]') is null or nd.site_nodename
           not in (?)
         )
         and (
         -- content dimension values (optional, if null all dimensions
           ? is null
           or sandstorm_kissearch_all_dimension_values_match(
             ?,
             nd.dimensionvalues
             )
         )
       order by score
       limit ?)
select
  -- select all search results
  a.result_id                       as result_id,
  a.result_type                     as result_type,
  a.result_title                    as result_title,
  -- max score wins
  max(a.score)                      as score,
  count(a.result_id)                as match_count,
  a.group_meta_data                 as group_meta_data,
  json_arrayagg(a.result_meta_data) as aggregate_meta_data
from all_results a
group by result_id, result_type
order by score desc
limit ?;


with     variant_results as (
  select *,
         match (search_name) against ( ? in boolean mode )        as
           score_name,
         match (search_description) against ( ? in boolean mode ) as
           score_description
  from oekokiste_core_shop_ecobox_variant ev
  where match (search_name, search_description) against ( ? in boolean
    mode )
    and ishidden = 0
),
         neos_content_results as (
           select *,
                  match (search_bucket_critical) against ( ? in boolean mode ) as
                    score_bucket_critical,
                  match (search_bucket_major) against ( ? in boolean mode ) as
                    score_bucket_major,
                  match (search_bucket_normal) against ( ? in boolean mode ) as
                    score_bucket_normal,
                  match (search_bucket_minor) against ( ? in boolean mode ) as
                    score_bucket_minor
           from neos_contentrepository_domain_model_nodedata
           where match (search_bucket_critical, search_bucket_major,
                   search_bucket_normal, search_bucket_minor) against ( ? in boolean mode )
         ),
         product_results as (
           select *,
                  match (p.search_name) against ( ? in boolean mode )        as
                    score_name,
                  match (p.search_searchterms) against ( ? in boolean mode ) as
                    score_searchterms,
                  match (p.search_infotxt) against ( ? in boolean mode )     as
                    score_infotxt
           from oekokiste_core_shop_product p
           where match (p.search_name, p.search_searchterms, p.search_infotxt)
             against ( ? in boolean mode )
             # do not include hidden products in search results
             and p.hidden = 0
         ),
         all_results as (
           -- union of all search types
           (select
             ev.id as result_id,
             ev.name as result_title,
             'eco_box_variant' as result_type,
             15 * ((20 * ev.score_name) + (5 * ev.score_description)) as
               score,
             json_object(
               'score', 15 * ((20 * ev.score_name) + (5 * ev.score_description))
               ) as result_meta_data,
             null as group_meta_data
           from variant_results ev
           order by score limit 10)
           union
           select
             v.id as result_id,
             v.name as result_title,
             'eco_box_variant' as result_type,
             10 * ((20 * p.score_name) + (5 * p.score_searchterms) + (1 *
                                                                      p.score_infotxt)) as score,
             json_object(
               'score', 10 * ((20 * p.score_name) + (5 * p.score_searchterms) +
                              (1 * p.score_infotxt)),
               'productIdentifier', p.id,
               'productName', p.name
               ) as result_meta_data,
             null as group_meta_data
           from oekokiste_core_shop_ecobox_variant v
                  inner join oekokiste_core_shop_ecoboxvariant_product vp
                             on v.id = vp.ecoBoxVariantId
                  inner join product_results p
                             on p.id = vp.productId
           order by score desc
           limit 10 union
select
  nd.document_id as result_id,
  nd.document_title as result_title,
  'neos_content' as result_type,
  (20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1
    * n.score_bucket_normal) + (0.5 * n.score_bucket_minor) as score,
  json_object(
    'score', (20 * n.score_bucket_critical) + (5 *
                                               n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 *
                                                                                                      n.score_bucket_minor),
    'nodeIdentifier', nd.identifier,
    'nodeType', nd.nodetype
    ) as result_meta_data,
  json_object(
    'primaryDomain', (select
                        concat(
                          if(d.scheme is not null, concat(d.scheme,
                                                          ':'), ''),
                          '//', d.hostname,
                          if(d.port is not null, concat(':', d.port),
                             '')
                          )
                      from neos_neos_domain_model_domain d
                      where d.persistence_object_identifier =
                            s.primarydomain
                        and d.active = 1),
    'documentNodeType', nd.document_nodetype,
    'siteNodeName', nd.site_nodename,
    'dimensionsHash', nd.dimensionshash,
    'dimensionValues', nd.dimensionvalues
    ) as group_meta_data
from neos_content_results n
       -- inner join filters hidden and deleted nodes
       inner join sandstorm_kissearch_nodes_and_their_documents nd
                  on nd.persistence_object_identifier =
                     n.persistence_object_identifier
       inner join neos_neos_domain_model_site s
                  on s.nodename = nd.site_nodename
where
  -- filter timed hidden before/after nodes
  not sandstorm_kissearch_any_timed_hidden(nd.timed_hidden,
                                           from_unixtime(?))
  -- filter deactivated sites
  and s.state = 1
  -- additional query parameters
  and (
  -- site node name (optional, if null all sites are searched)
    json_value(json_array(?), '$[0]') is null or nd.site_nodename
    in (?)
  )
  and (
    json_value(json_array(?), '$[0]') is null or nd.site_nodename
    not in (?)
  )
  and (
  -- content dimension values (optional, if null all dimensions
  are searched)
  ? is null
            or sandstorm_kissearch_all_dimension_values_match(
                    ?,
                    nd.dimensionvalues
            )
        )
order by score desc
  limit ? union
select
  r.id as result_id,
  r.name as result_title,
  'product' as result_type,
  9 * ((20 * score_name) + (5 * score_searchterms) + (1 *
                                                      score_infotxt)) as score,
  json_object(
    'score', 9 * ((20 * score_name) + (5 * score_searchterms) + (1 *
                                                                 score_infotxt))
    ) as result_meta_data,
  null as group_meta_data
from product_results r
order by score desc
limit ?
)
select
  -- select all search results
  a.result_id as result_id,
  a.result_type as result_type,
  a.result_title as result_title,
  -- max score wins
  -- TODO discuss, if max(score) vs. sum(score) vs. set mode via
  API
    max(a.score) as score,
  count(a.result_id) as match_count,
  a.group_meta_data as group_meta_data,
  json_arrayagg(a.result_meta_data) as aggregate_meta_data
from all_results a
     -- group by result id and type in case multiple merging query parts
  return the same result
group by result_id, result_type
order by score desc
  limit ?;



with     neos_content_results as (
  select *,
         match (search_bucket_critical) against ( :query in boolean mode ) as score_bucket_critical,
         match (search_bucket_major) against ( :query in boolean mode ) as score_bucket_major,
         match (search_bucket_normal) against ( :query in boolean mode ) as score_bucket_normal,
         match (search_bucket_minor) against ( :query in boolean mode ) as score_bucket_minor
  from neos_contentrepository_domain_model_nodedata
  where match (search_bucket_critical, search_bucket_major, search_bucket_normal, search_bucket_minor) against ( :query in boolean mode )
),
         all_results as (
           -- union of all search types
           (    select
                  nd.document_id as result_id,
                  nd.document_title as result_title,
                  'neos_content' as result_type,
                  max((20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor)) as score,
                  count(nd.document_id) as match_count,
                  json_arrayagg(json_object(
                    'score', (20 * n.score_bucket_critical) + (5 * n.score_bucket_major) + (1 * n.score_bucket_normal) + (0.5 * n.score_bucket_minor),
                    'nodeIdentifier', nd.identifier,
                    'nodeType', nd.nodetype
                    )) as aggregate_meta_data,
                  json_object(
                    'primaryDomain', (select
                                        concat(
                                          if(d.scheme is not null, concat(d.scheme, ':'), ''),
                                          '//', d.hostname,
                                          if(d.port is not null, concat(':', d.port), '')
                                          )
                                      from neos_neos_domain_model_domain d
                                      where d.persistence_object_identifier = s.primarydomain
                                        and d.active = 1),
                    'documentNodeType', nd.document_nodetype,
                    'siteNodeName', nd.site_nodename,
                    'dimensionsHash', nd.dimensionshash,
                    'dimensionValues', nd.dimensionvalues
                    ) as group_meta_data
                  -- for all nodes matching search terms, we have to find the corresponding document node
                  -- to link to the content in the search result rendering
                from neos_content_results n
                       -- inner join filters hidden and deleted nodes
                       inner join sandstorm_kissearch_nodes_and_their_documents nd
                                  on nd.persistence_object_identifier = n.persistence_object_identifier
                       inner join neos_neos_domain_model_site s
                                  on s.nodename = nd.site_nodename
                where
                  -- filter timed hidden before/after nodes
                  not sandstorm_kissearch_any_timed_hidden(nd.timed_hidden, from_unixtime(:nowTime))
                  -- filter deactivated sites
                  and s.state = 1
                  -- additional query parameters
                  and (
                  -- site node name (optional, if null all sites are searched)
                    json_value(json_array(:neosContentSiteNodeName), '$[0]') is null or nd.site_nodename in (:neosContentSiteNodeName)
                  )
                  and (
                    json_value(json_array(:neosContentExcludedSiteNodeName), '$[0]') is null or nd.site_nodename not in (:neosContentExcludedSiteNodeName)
                  )
                  and (
                  -- content dimension values (optional, if null all dimensions are searched)
                    :neosContentDimensionValues is null
                    or sandstorm_kissearch_all_dimension_values_match(
                      :neosContentDimensionValues,
                      nd.dimensionvalues
                      )
                  )
                group by nd.document_id
           )
             order by score desc
             limit :limit_neos_content
         )
select
  -- select all search results
  a.result_id as result_id,
  a.result_type as result_type,
  a.result_title as result_title,
  -- max score wins
  -- TODO discuss, if max(score) vs. sum(score) vs. set mode via API
  a.score as score,
  a.match_count as match_count,
  a.group_meta_data as group_meta_data,
  a.aggregate_meta_data as aggregate_meta_data
from all_results a
     -- group by result id and type in case multiple merging query parts return the same result
order by score desc;
