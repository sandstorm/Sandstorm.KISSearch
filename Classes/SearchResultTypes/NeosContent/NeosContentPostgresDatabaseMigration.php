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

        return implode("\n", $sqlQueries);
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
                    FulltextExtractionMode::EXTRACT_INTO_SINGLE_BUCKET => $jsonExtractor,
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
