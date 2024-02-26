<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\InvalidConfigurationException;
use Sandstorm\KISSearch\PostgresTS\PostgresFulltextSearchConfiguration;
use Sandstorm\KISSearch\PostgresTS\PostgresFulltextSearchMode;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseMigrationInterface;
use Sandstorm\KISSearch\SearchResultTypes\SearchBucket;

#[Proxy(false)]
class NeosContentPostgresDatabaseMigration implements DatabaseMigrationInterface
{

    public const COLUMN_SEARCH_ALL = 'search_all';

    private readonly NodeTypesSearchConfiguration $nodeTypesSearchConfiguration;
    private readonly PostgresFulltextSearchConfiguration $postgresFulltextSearchConfiguration;

    /**
     * @param PostgresFulltextSearchConfiguration $postgresFulltextSearchConfiguration
     * @param NodeTypesSearchConfiguration $nodeTypesSearchConfiguration
     */
    public function __construct(NodeTypesSearchConfiguration $nodeTypesSearchConfiguration, PostgresFulltextSearchConfiguration $postgresFulltextSearchConfiguration)
    {
        $this->nodeTypesSearchConfiguration = $nodeTypesSearchConfiguration;
        $this->postgresFulltextSearchConfiguration = $postgresFulltextSearchConfiguration;
    }

    function versionHash(): string
    {
        // TODO add postgres fulltext search configuration to hash
        return $this->nodeTypesSearchConfiguration->buildVersionHash('1690488603');
    }

    function up(): string
    {
        $sqlQueries = [
            <<<SQL
                -- extract content of specific html tags
                create or replace function sandstorm_kissearch_extract_html_content(
                  input_content text,
                  variadic html_tags text[]
                )
                  returns text
                as
                $$
                begin
                  return (
                    select
                      string_agg(m.tag_contents, ' ')
                    from (
                      select
                        array_to_string(
                          regexp_matches(
                            sandstorm_kissearch_extract_html_content.input_content,
                            '<(?:' || array_to_string(sandstorm_kissearch_extract_html_content.html_tags, '|') || ')(?: .*?)?>([^<>]*?)</(?:' || array_to_string(sandstorm_kissearch_extract_html_content.html_tags, '|') || ')>',
                            'gmi'
                          ),
                          ''
                        ) as tag_contents
                      ) m);
                end
                $$
                  language 'plpgsql' immutable
                                    parallel safe;
            SQL,
            <<<SQL
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
            SQL
        ];
        $defaultTsConfig = $this->postgresFulltextSearchConfiguration->getDefaultTsConfig();

        if ($this->postgresFulltextSearchConfiguration->getMode() === PostgresFulltextSearchMode::CONTENT_DIMENSION) {
            $contentDimensionName = $this->postgresFulltextSearchConfiguration->getContentDimensionConfiguration()->getDimensionName();
            $dimensionValueMapping = json_encode($this->postgresFulltextSearchConfiguration->getContentDimensionConfiguration()->getDimensionValueMapping());

            $sqlQueries[] = <<<SQL
                    -- get ts config from node content dimension
                    create or replace function sandstorm_kissearch_get_ts_config_for_node(
                      node_persistence_object_identifier varchar(40),
                      node_dimensionvalues jsonb
                    )
                      returns regconfig
                    as
                    $$
                    declare
                      default_ts_config              regconfig  = '$defaultTsConfig';
                      language_dimension_name        text       = '$contentDimensionName';
                      dimension_value_mapping        jsonb      = '$dimensionValueMapping';
                      node_language_dimension_value  text;
                      ts_config_from_dimensionvalues regconfig;
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
                      return ts_config_from_dimensionvalues::regconfig;
                    end
                    $$
                      language 'plpgsql' immutable
                                         parallel safe;
                SQL;
        }

        $columnNameBucketCritical = self::columnNameBucketCritical();
        $columnNameBucketMajor = self::columnNameBucketMajor();
        $columnNameBucketNormal = self::columnNameBucketNormal();
        $columnNameBucketMinor = self::columnNameBucketMinor();
        $columnNameAll = self::COLUMN_SEARCH_ALL;

        $fulltextExtractorsByNodeTypeForCritical = self::buildPostgresFulltextExtractorForBucket(
            $this->nodeTypesSearchConfiguration->getExtractorsForCritical(), SearchBucket::CRITICAL);
        $fulltextExtractorsByNodeTypeForMajor = self::buildPostgresFulltextExtractorForBucket(
            $this->nodeTypesSearchConfiguration->getExtractorsForMajor(), SearchBucket::MAJOR);
        $fulltextExtractorsByNodeTypeForNormal = self::buildPostgresFulltextExtractorForBucket(
            $this->nodeTypesSearchConfiguration->getExtractorsForNormal(), SearchBucket::NORMAL);
        $fulltextExtractorsByNodeTypeForMinor = self::buildPostgresFulltextExtractorForBucket(
            $this->nodeTypesSearchConfiguration->getExtractorsForMinor(), SearchBucket::MINOR);

        $tsConfigExpression = match ($this->postgresFulltextSearchConfiguration->getMode()) {
            PostgresFulltextSearchMode::DEFAULT => sprintf("'%s'", $this->postgresFulltextSearchConfiguration->getDefaultTsConfig()),
            PostgresFulltextSearchMode::CONTENT_DIMENSION => 'sandstorm_kissearch_get_ts_config_for_node(persistence_object_identifier, dimensionvalues)',
            default => throw new InvalidConfigurationException(
                sprintf(
                    "Postgres language mode '%s' not implemented",
                    $this->postgresFulltextSearchConfiguration->getMode()->name
                ),
                1690493667
            )
        };

        $toTsVector = function (string $expression) use ($tsConfigExpression) {
            return sprintf(
                'to_tsvector(%s, %s)',
                $tsConfigExpression,
                $expression
            );
        };

        $tsVectorCritical = $toTsVector($fulltextExtractorsByNodeTypeForCritical);
        $tsVectorMajor = $toTsVector($fulltextExtractorsByNodeTypeForMajor);
        $tsVectorNormal = $toTsVector($fulltextExtractorsByNodeTypeForNormal);
        $tsVectorMinor = $toTsVector($fulltextExtractorsByNodeTypeForMinor);
        $allExpression = implode(" || ' ' || ", array_map(function (string $expression) {
            return sprintf("coalesce(%s, '')", $expression);
        }, [
            $fulltextExtractorsByNodeTypeForCritical,
            $fulltextExtractorsByNodeTypeForMajor,
            $fulltextExtractorsByNodeTypeForNormal,
            $fulltextExtractorsByNodeTypeForMinor
        ]));
        $allExpressionWithEmptyStringHandling = sprintf(
            "case when (%s is null and %s is null and %s is null and %s is null) then null else %s end",
            $fulltextExtractorsByNodeTypeForCritical,
            $fulltextExtractorsByNodeTypeForMajor,
            $fulltextExtractorsByNodeTypeForNormal,
            $fulltextExtractorsByNodeTypeForMinor,
            $allExpression
        );
        $tsVectorAll = $toTsVector($allExpressionWithEmptyStringHandling);

        $sqlQueries[] = <<<SQL
            alter table neos_contentrepository_domain_model_nodedata
                add $columnNameBucketCritical tsvector generated always as (
                    $tsVectorCritical
                ) stored,
                add $columnNameBucketMajor tsvector generated always as (
                    $tsVectorMajor
                ) stored,
                add $columnNameBucketNormal tsvector generated always as (
                    $tsVectorNormal
                ) stored,
                add $columnNameBucketMinor tsvector generated always as (
                    $tsVectorMinor
                ) stored,
                add $columnNameAll tsvector generated always as (
                    $tsVectorAll
                ) stored;
        SQL;

        $sqlQueries[] = <<<SQL
            create index idx_kissearch_nodedata_critical on neos_contentrepository_domain_model_nodedata USING gin($columnNameBucketCritical);
            create index idx_kissearch_nodedata_major on neos_contentrepository_domain_model_nodedata USING gin($columnNameBucketMajor);
            create index idx_kissearch_nodedata_normal on neos_contentrepository_domain_model_nodedata USING gin($columnNameBucketNormal);
            create index idx_kissearch_nodedata_minor on neos_contentrepository_domain_model_nodedata USING gin($columnNameBucketMinor);
            create index idx_kissearch_nodedata_all on neos_contentrepository_domain_model_nodedata USING gin($columnNameAll);
        SQL;

        $allDocumentNodeTypes = self::nodeTypeNamesToQuotedCommaSeparatedString($this->nodeTypesSearchConfiguration->getDocumentNodeTypeNames());
        $allContentNodeTypes = self::nodeTypeNamesToQuotedCommaSeparatedString($this->nodeTypesSearchConfiguration->getContentNodeTypeNames());

        $sqlQueries[] = <<<SQL
            create or replace function sandstorm_kissearch_is_document(
              nodetype_name text
            ) returns boolean
            as
            $$
            begin
              return sandstorm_kissearch_is_document.nodetype_name in
                     ($allDocumentNodeTypes);
            end;
            $$ language 'plpgsql' immutable
                                  parallel safe;
        SQL;

        $sqlQueries[] = <<<SQL
            create or replace function sandstorm_kissearch_is_content(
              nodetype_name text
            ) returns boolean
            as
            $$
            begin
              return sandstorm_kissearch_is_content.nodetype_name in
                     ($allContentNodeTypes);
            end;
            $$ language 'plpgsql' immutable
                                  parallel safe;
        SQL;

        $sqlQueries[] = <<<SQL
            create materialized view if not exists sandstorm_kissearch_nodes_and_their_documents as
            with recursive nodes_and_their_documents as
                             (select n.persistence_object_identifier,
                                     n.identifier,
                                     n.pathhash,
                                     n.parentpathhash,
                                     n.nodetype,
                                     null::varchar(32)        as dimensionshash,
                                     null::jsonb              as dimensionvalues,
                                     null::text               as site_nodename,
                                     n.identifier             as document_id,
                                     n.properties ->> 'title' as document_title,
                                     n.nodetype               as document_nodetype,
                                     not n.hidden             as not_hidden,
                                     not n.removed            as not_removed,
                                     case
                                       when n.hiddenbeforedatetime is not null or n.hiddenafterdatetime is not null then
                                         jsonb_build_array(
                                           jsonb_build_object(
                                             'before', n.hiddenbeforedatetime,
                                             'after', n.hiddenafterdatetime
                                           )
                                         )
                                       else
                                         '[]'::jsonb
                                       end                    as timed_hidden
                              from neos_contentrepository_domain_model_nodedata n
                              where n.path = '/sites'
                                and not n.hidden
                                and not n.removed
                                and n.workspace = 'live'
                              union
                              select n.persistence_object_identifier,
                                     n.identifier,
                                     n.pathhash,
                                     n.parentpathhash,
                                     n.nodetype,
                                     n.dimensionshash,
                                     n.dimensionvalues,
                                     -- site node name
                                     coalesce(substring(n.path from '^/sites/([a-z0-9\-]+)$'), r.site_nodename) as site_nodename,
                                     -- document id
                                     case
                                       when sandstorm_kissearch_is_document(n.nodetype) then n.identifier
                                       else r.document_id end                                           as document_id,
                                     case
                                       when sandstorm_kissearch_is_document(n.nodetype) then n.properties ->> 'title'
                                       else r.document_title end                                        as document_title,
                                     case
                                       when sandstorm_kissearch_is_document(n.nodetype) then n.nodetype
                                       else r.document_nodetype end                                     as document_nodetype,
                                     not n.hidden and r.not_hidden                                      as not_hidden,
                                     not n.removed and r.not_removed                                    as not_removed,
                                     case
                                       when n.hiddenbeforedatetime is not null or n.hiddenafterdatetime is not null then
                                         r.timed_hidden || jsonb_build_object(
                                           'before', n.hiddenbeforedatetime,
                                           'after', n.hiddenafterdatetime
                                                           )
                                       else r.timed_hidden end                                          as timed_hidden
                              from neos_contentrepository_domain_model_nodedata n,
                                   nodes_and_their_documents r
                              where r.pathhash = n.parentpathhash
                                and workspace = 'live'
                                and (r.dimensionshash is null or n.dimensionshash = r.dimensionshash))
            select nd.persistence_object_identifier as persistence_object_identifier,
                   nd.identifier                    as identifier,
                   nd.document_id                   as document_id,
                   nd.document_title                as document_title,
                   nd.document_nodetype             as document_nodetype,
                   nd.nodetype                      as nodetype,
                   nd.dimensionshash                as dimensionshash,
                   nd.dimensionvalues               as dimensionvalues,
                   nd.site_nodename                 as site_nodename,
                   case
                     when jsonb_array_length(nd.timed_hidden) > 0 then
                       nd.timed_hidden
                     end                            as timed_hidden
            from nodes_and_their_documents nd
            where nd.site_nodename is not null
              and nd.not_hidden
              and nd.not_removed
              and (
              sandstorm_kissearch_is_document(nd.nodetype)
                or sandstorm_kissearch_is_content(nd.nodetype)
              );
        SQL;

        $sqlQueries[] = <<<SQL
            create or replace function sandstorm_kissearch_all_dimension_values_match(
              dimension_values_filter jsonb,
              dimension_values jsonb
            )
              returns boolean
            as
            $$
            begin
              return (select not exists(
                select 1 from jsonb_array_elements(sandstorm_kissearch_all_dimension_values_match.dimension_values_filter) f
                         where (sandstorm_kissearch_all_dimension_values_match.dimension_values ->> (f ->> 'dimension_name'))::jsonb ->> (f ->> 'index_key') != f ->> 'filter_value'
              ));
            end
            $$
              language 'plpgsql' immutable
                                 parallel safe;
        SQL;

        $sqlQueries[] = <<<SQL
            create or replace function sandstorm_kissearch_any_timed_hidden(
              timed_hidden jsonb,
              now_time timestamptz
            )
              returns boolean
            as
            $$
            begin
              return (select sandstorm_kissearch_any_timed_hidden.timed_hidden is not null
                               and exists(select 1
                                          from jsonb_array_elements(sandstorm_kissearch_any_timed_hidden.timed_hidden) as th
                                          where case
                                                  when th::jsonb ->> 'before' is null
                                                    then (th::jsonb ->> 'after')::timestamptz <
                                                         sandstorm_kissearch_any_timed_hidden.now_time
                                                  when th::jsonb ->> 'after' is null
                                                    then (th::jsonb ->> 'before')::timestamptz >
                                                         sandstorm_kissearch_any_timed_hidden.now_time
                                                  else sandstorm_kissearch_any_timed_hidden.now_time not between (th::jsonb ->> 'before')::timestamptz and (th::jsonb ->> 'after')::timestamptz
                                                  end));
            end
            $$
              language 'plpgsql' immutable
                                 parallel safe;
        SQL;

        return implode("\n", $sqlQueries);
    }

    private static function nodeTypeNamesToQuotedCommaSeparatedString(array $nodeTypeNames): string {
        return implode(
            ', ',
            array_map(
                function(string $documentNodeType) {
                    return sprintf("'%s'", $documentNodeType);
                },
                $nodeTypeNames
            )
        );
    }

    function down(): string
    {
        $columnNameBucketCritical = self::columnNameBucketCritical();
        $columnNameBucketMajor = self::columnNameBucketMajor();
        $columnNameBucketNormal = self::columnNameBucketNormal();
        $columnNameBucketMinor = self::columnNameBucketMinor();
        $columnNameAll = self::COLUMN_SEARCH_ALL;

        $sqlQueries = [
            <<<SQL
                alter table neos_contentrepository_domain_model_nodedata
                    drop column if exists $columnNameBucketCritical,
                    drop column if exists $columnNameBucketMajor,
                    drop column if exists $columnNameBucketNormal,
                    drop column if exists $columnNameBucketMinor,
                    drop column if exists $columnNameAll;
            SQL
        ];

        $sqlQueries[] = <<<SQL
            drop function if exists sandstorm_kissearch_get_ts_config_for_node(varchar(40), jsonb);
            drop function if exists sandstorm_kissearch_extract_html_content(text, text[]);
            drop function if exists sandstorm_kissearch_remove_html_tags_with_content(text, text[]);
            drop function if exists sandstorm_kissearch_all_dimension_values_match(jsonb, jsonb);
            drop function if exists sandstorm_kissearch_any_timed_hidden(jsonb, timestamptz);
        SQL;

        $sqlQueries[] = <<<SQL
            drop index if exists idx_kissearch_nodedata_critical;
            drop index if exists idx_kissearch_nodedata_major;
            drop index if exists idx_kissearch_nodedata_normal;
            drop index if exists idx_kissearch_nodedata_minor;
            drop index if exists idx_kissearch_nodedata_all;
        SQL;

        $sqlQueries[] = <<<SQL
            drop materialized view if exists sandstorm_kissearch_nodes_and_their_documents;
        SQL;

        return implode("\n", $sqlQueries);
    }

    private function buildPostgresFulltextExtractorForBucket(array $extractorsByNodeType, SearchBucket $targetBucket): string
    {
        $sqlCases = [];

        /** @var array $propertyExtractions */
        foreach ($extractorsByNodeType as $nodeTypeName => $propertyExtractions) {
            $sql = " when nodetype = '$nodeTypeName' then ";
            $thenSql = [];
            /** @var NodePropertyFulltextExtraction $propertyExtraction */
            foreach ($propertyExtractions as $propertyExtraction) {
                $propertyName = $propertyExtraction->getPropertyName();
                $jsonExtractor = "properties ->> '$propertyName'";
                $thenSql[] = match ($propertyExtraction->getMode()) {
                    FulltextExtractionMode::EXTRACT_INTO_SINGLE_BUCKET => "($jsonExtractor)",
                    FulltextExtractionMode::EXTRACT_HTML_TAGS => match ($targetBucket) {
                        SearchBucket::CRITICAL => "sandstorm_kissearch_extract_html_content($jsonExtractor, 'h1', 'h2')",
                        SearchBucket::MAJOR => "sandstorm_kissearch_extract_html_content($jsonExtractor, 'h3', 'h4', 'h5', 'h6')",
                        SearchBucket::NORMAL, SearchBucket::MINOR => "sandstorm_kissearch_remove_html_tags_with_content($jsonExtractor, 'h1', 'h2', 'h3', 'h4', 'h5', 'h6')"
                    }
                };
            }
            if (count($thenSql) === 1) {
                // only one property for node type
                $sql .= $thenSql[0];
            } else if (count($thenSql) > 1) {
                // multiple properties for node type are string concatenated in fulltext extraction
                $sql .= implode(" || ' ' || ", $thenSql);
            } else {
                // null, in case that no property is configured (this shouldn't happen by regular uses of the API)
                $sql .= "null";
            }
            $sqlCases[] = $sql;
        }
        if (empty($sqlCases)) {
            return "null";
        } else {
            $sqlCases[] = " else null";
            return sprintf("case\n %s\n end", implode("\n", $sqlCases));
        }
    }

    /**
     * @return string
     */
    static function columnNameBucketCritical(): string
    {
        return sprintf('search_bucket_%s', SearchBucket::CRITICAL->value);
    }

    /**
     * @return string
     */
    static function columnNameBucketMajor(): string
    {
        return sprintf('search_bucket_%s', SearchBucket::MAJOR->value);
    }

    /**
     * @return string
     */
    static function columnNameBucketNormal(): string
    {
        return sprintf('search_bucket_%s', SearchBucket::NORMAL->value);
    }

    /**
     * @return string
     */
    static function columnNameBucketMinor(): string
    {
        return sprintf('search_bucket_%s', SearchBucket::MINOR->value);
    }

}
