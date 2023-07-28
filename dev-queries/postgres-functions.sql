-- get postgres version
select version() as version;

-- get all available ts configs (a.k.a. languages)
select cfgname
from pg_ts_config;

-- get ts config from node content dimension
create or replace function sandstorm_kissearch_get_ts_config_for_node(
  node_persistence_object_identifier varchar(40),
  node_dimensionvalues jsonb
)
  returns name
as
$$
declare
  default_ts_config              name  = 'simple';
  language_dimension_name        text  = 'language';
  dimension_value_mapping        jsonb = '{"de": "german", "en": "english"}';
  node_language_dimension_value  text;
  ts_config_from_dimensionvalues name;
begin
  select sandstorm_kissearch_get_ts_config_for_node.node_dimensionvalues -> language_dimension_name ->> '0'
  into node_language_dimension_value;
  if (node_language_dimension_value is null) then
    raise log 'no language dimension value found for node "%", using default ts config "%"',
      sandstorm_kissearch_get_ts_config_for_node.node_persistence_object_identifier,
      default_ts_config;
    return default_ts_config;
  end if;
  select dimension_value_mapping ->> node_language_dimension_value
  into ts_config_from_dimensionvalues;
  if (ts_config_from_dimensionvalues is null) then
    raise log 'no ts config mapping found for language dimension value "%" for node "%", using default ts config "%"',
      node_language_dimension_value,
      sandstorm_kissearch_get_ts_config_for_node.node_persistence_object_identifier,
      default_ts_config;
    return default_ts_config;
  end if;
  return ts_config_from_dimensionvalues::name;
end
$$
  language 'plpgsql' immutable
                     parallel safe;

select sandstorm_kissearch_get_ts_config_for_node('foo-node-id', '{
  "language": {
    "0": "en"
  }
}');
select sandstorm_kissearch_get_ts_config_for_node('foo-node-id', '{
  "language": {
    "0": "en1"
  }
}');
select sandstorm_kissearch_get_ts_config_for_node('foo-node-id', '{
  "language1": {
    "0": "en"
  }
}');

-- get ts config from node content dimension
create or replace function sandstorm_kissearch_get_ts_config_for_node(
  node_persistence_object_identifier varchar(40),
  node_dimensionvalues jsonb
)
  returns name
as
$$
declare
  default_ts_config              name  = 'simple';
  language_dimension_name        text  = 'language';
  dimension_value_mapping        jsonb = '{"de": "german", "en": "english"}';
  node_language_dimension_value  text;
  ts_config_from_dimensionvalues name;
begin
  select sandstorm_kissearch_get_ts_config_for_node.node_dimensionvalues -> language_dimension_name ->> '0'
  into node_language_dimension_value;
  if (node_language_dimension_value is null) then
    raise log 'no language dimension value found for node "%", using default ts config "%"',
      sandstorm_kissearch_get_ts_config_for_node.node_persistence_object_identifier,
      default_ts_config;
    return default_ts_config;
  end if;
  select dimension_value_mapping ->> node_language_dimension_value
  into ts_config_from_dimensionvalues;
  if (ts_config_from_dimensionvalues is null) then
    raise log 'no ts config mapping found for language dimension value "%" for node "%", using default ts config "%"',
      node_language_dimension_value,
      sandstorm_kissearch_get_ts_config_for_node.node_persistence_object_identifier,
      default_ts_config;
    return default_ts_config;
  end if;
  return ts_config_from_dimensionvalues::name;
end
$$
  language 'plpgsql' immutable
                     parallel safe;


select ('{
  "a": "foo <h1>bar<\/h1>"
}'::jsonb) ->> 'a';

select to_tsvector('simple', 'foo <h1>bar</h1>') || to_tsvector('simple', 'bar baz');

-- extract content of specific html tags
create or replace function sandstorm_kissearch_extract_html_content(
  input_content text,
  variadic html_tags text[]
)
  returns text
as
$$
begin
  return (select string_agg(m.match, ' ')
          from (select array_to_string(
                         regexp_matches(
                           sandstorm_kissearch_extract_html_content.input_content,
                           '<(?:' || array_to_string(sandstorm_kissearch_extract_html_content.html_tags, '|') ||
                           ')(?: .*?)?>([^<>]*?)</(?:' ||
                           array_to_string(sandstorm_kissearch_extract_html_content.html_tags, '|') || ')>',
                           'gmi'
                           ),
                         ''
                         ) as match) m);
end
$$
  language 'plpgsql' immutable
                     parallel safe;

select sandstorm_kissearch_extract_html_content('foo <h1>heading 1</h1> asdasd <h2>heading 2</h2>', 'h1', 'h2');

-- remove all HTML tags with content
create or replace function sandstorm_kissearch_remove_html_tags_with_content(
  input_content text,
  variadic html_tags text[]
)
  returns text
as
$$
begin
  return regexp_replace(
    sandstorm_kissearch_remove_html_tags_with_content.input_content,
    '<(' || array_to_string(sandstorm_kissearch_remove_html_tags_with_content.html_tags, '|') ||
    ')( .*?)?>([^<>]*?)</(' ||
    array_to_string(sandstorm_kissearch_remove_html_tags_with_content.html_tags, '|') || ')>',
    '',
    'gmi'
    );
end
$$
  language 'plpgsql' immutable
                     parallel safe;

select sandstorm_kissearch_remove_html_tags_with_content('foo <h1>heading 1</h1> asdasd <h2>heading 2</h2>', 'h1', 'h2',
                                                         'h3', 'h4', 'h5', 'h6');
