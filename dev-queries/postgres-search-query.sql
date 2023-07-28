select n.* from neos_contentrepository_domain_model_nodedata n
where n.search_all @@ to_tsquery('simple', 'neos');
