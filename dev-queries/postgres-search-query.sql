select
  n.identifier,
  coalesce(ts_rank_cd(n.search_bucket_critical, :search), 0) score_critical,
  coalesce(ts_rank_cd(n.search_bucket_major, :search), 0) score_major,
  coalesce(ts_rank_cd(n.search_bucket_normal, :search), 0) score_normal,
  coalesce(ts_rank_cd(n.search_bucket_minor, :search), 0) score_minor
  -- ts_rank_cd(n.search_all, :search) score_all
from neos_contentrepository_domain_model_nodedata n
where n.search_bucket_critical @@ :search
  -- n.search_all @@ :search;
--order by score_all desc
--limit 20;
  ;

select jsonb_array_length(jsonb_build_array(1, 2, 3)) > 0;
