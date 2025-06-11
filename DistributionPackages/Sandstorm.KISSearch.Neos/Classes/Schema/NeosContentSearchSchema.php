<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Schema;

use Neos\Flow\Annotations\Inject;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\DBAbstraction\MySQLHelper;
use Sandstorm\KISSearch\Api\Schema\SearchSchemaInterface;
use Sandstorm\KISSearch\Neos\NeosContentSearchResultType;
use Sandstorm\KISSearch\Neos\Schema\Model\FulltextExtractionMode;
use Sandstorm\KISSearch\Neos\Schema\Model\NodePropertyFulltextExtraction;
use Sandstorm\KISSearch\Neos\SearchBucket;

// TODO content repository ID for dynamic table name
#[Scope('singleton')]
class NeosContentSearchSchema implements SearchSchemaInterface
{

    #[Inject]
    protected NeosContentSchemaConfiguration $schemaConfiguration;

    function createSchema(DatabaseType $databaseType): array
    {
        // TODO postgres
        $nodeTypesSearchConfiguration = $this->schemaConfiguration->getNodeTypesSearchConfiguration();

        return [
            self::mariaDB_create_generatedSearchBucketColumns(
                $nodeTypesSearchConfiguration->getExtractorsForCritical(),
                $nodeTypesSearchConfiguration->getExtractorsForMajor(),
                $nodeTypesSearchConfiguration->getExtractorsForNormal(),
                $nodeTypesSearchConfiguration->getExtractorsForMinor()
            ),
            self::mariaDB_create_tableNodesAndTheirDocuments(),
            self::mariaDB_create_functionIsDocumentNodeType($nodeTypesSearchConfiguration->getDocumentNodeTypeNames()),
            self::mariaDB_create_functionIsContentNodeType($nodeTypesSearchConfiguration->getContentNodeTypeNames()),
            self::mariaDB_create_functionPopulateNodesAndTheirDocuments(),
            self::mariaDB_create_functionAllDimensionValuesMatch(),

            // move to data update command
            //self::mariaDB_call_functionPopulateNodesAndTheirDocuments()
        ];
    }

    function dropSchema(DatabaseType $databaseType): array
    {
        // TODO postgres
        return [
            self::mariaDB_drop_generatedSearchBucketColumns(),
            self::mariaDB_drop_tableNodesAndTheirDocuments(),
            self::mariaDB_drop_functionIsDocumentNodeType(),
            self::mariaDB_drop_functionIsContentNodeType(),
            self::mariaDB_drop_functionPopulateNodesAndTheirDocuments(),
            self::mariaDB_drop_functionAllDimensionValuesMatch(),
        ];
    }

    // ## generated search bucket columns

    private static function mariaDB_create_generatedSearchBucketColumns(
        array $extractorsForCritical,
        array $extractorsForMajor,
        array $extractorsForNormal,
        array $extractorsForMinor,
    ): string {
        $columnNameBucketCritical = NeosContentSearchResultType::BUCKET_COLUMN_CRITICAL;
        $columnNameBucketMajor = NeosContentSearchResultType::BUCKET_COLUMN_MAJOR;
        $columnNameBucketNormal = NeosContentSearchResultType::BUCKET_COLUMN_NORMAL;
        $columnNameBucketMinor = NeosContentSearchResultType::BUCKET_COLUMN_MINOR;

        $fulltextExtractorsByNodeTypeForCritical = self::buildMySQLFulltextExtractionForBucket(
            $extractorsForCritical,
            SearchBucket::CRITICAL
        );
        $fulltextExtractorsByNodeTypeForMajor = self::buildMySQLFulltextExtractionForBucket(
            $extractorsForMajor,
            SearchBucket::MAJOR
        );
        $fulltextExtractorsByNodeTypeForNormal = self::buildMySQLFulltextExtractionForBucket(
            $extractorsForNormal,
            SearchBucket::NORMAL
        );
        $fulltextExtractorsByNodeTypeForMinor = self::buildMySQLFulltextExtractionForBucket(
            $extractorsForMinor,
            SearchBucket::MINOR
        );

        return <<<SQL
                alter table cr_default_p_graph_node
                    add $columnNameBucketCritical text generated always as (
                        $fulltextExtractorsByNodeTypeForCritical
                    ) stored,
                    add $columnNameBucketMajor text generated always as (
                        $fulltextExtractorsByNodeTypeForMajor
                    ) stored,
                    add $columnNameBucketNormal text generated always as (
                        $fulltextExtractorsByNodeTypeForNormal
                    ) stored,
                    add $columnNameBucketMinor text generated always as (
                        $fulltextExtractorsByNodeTypeForMinor
                    ) stored;
                create fulltext index idx_neos_content_all_buckets on cr_default_p_graph_node ($columnNameBucketCritical, $columnNameBucketMajor, $columnNameBucketNormal, $columnNameBucketMinor);
                create fulltext index idx_neos_content_bucket_critical on cr_default_p_graph_node ($columnNameBucketCritical);
                create fulltext index idx_neos_content_bucket_major on cr_default_p_graph_node ($columnNameBucketMajor);
                create fulltext index idx_neos_content_bucket_normal on cr_default_p_graph_node ($columnNameBucketNormal);
                create fulltext index idx_neos_content_bucket_minor on cr_default_p_graph_node ($columnNameBucketMinor);
        SQL;
    }

    private static function mariaDB_drop_generatedSearchBucketColumns(): string
    {
        $columnNameBucketCritical = NeosContentSearchResultType::BUCKET_COLUMN_CRITICAL;
        $columnNameBucketMajor = NeosContentSearchResultType::BUCKET_COLUMN_MAJOR;
        $columnNameBucketNormal = NeosContentSearchResultType::BUCKET_COLUMN_NORMAL;
        $columnNameBucketMinor = NeosContentSearchResultType::BUCKET_COLUMN_MINOR;

        return <<<SQL
            drop index if exists idx_neos_content_all_buckets on cr_default_p_graph_node;
            drop index if exists idx_neos_content_bucket_critical on cr_default_p_graph_node;
            drop index if exists idx_neos_content_bucket_major on cr_default_p_graph_node;
            drop index if exists idx_neos_content_bucket_normal on cr_default_p_graph_node;
            drop index if exists idx_neos_content_bucket_minor on cr_default_p_graph_node;
            alter table cr_default_p_graph_node
                drop column if exists $columnNameBucketCritical,
                drop column if exists $columnNameBucketMajor,
                drop column if exists $columnNameBucketNormal,
                drop column if exists $columnNameBucketMinor;
        SQL;
    }

    /**
     * The general idea here is, to create fulltext extractors based on generated columns.
     * Neos Nodes persist their properties inside a JSON object, so each row in the nodedata table
     * have different extractors based on their respective Node Type. So the outermost structure of the
     * generated column expression is a huge switch-case statement handling each specific configured Node Type.
     * Inside the specific cases for each Node Type, all properties are extracted and sanitized from the properties JSON column.
     * Generically, the DDL structure looks like:
     *
     * search_bucket_x => case
     *     when nodetype = 'NodeType.A' then
     *          concat(
     *              fulltext_extractor_for_property_X,
     *              ' ',
     *              fulltext_extractor_for_property_Y,
     *              ' ',
     *              ...
     *          )
     *     when nodetype = 'NodeType.B' then
     *          concat(
     *              fulltext_extractor_for_property_P,
     *              ' ',
     *              fulltext_extractor_for_property_Q,
     *              ' ',
     *              ...
     *          )
     *     else null
     * end
     *
     * Fulltext extractors most likely look like:
     *
     * json_extract(properties, '$.nodePropertyName')
     *
     * ... wrapped by lots of replace function calls to sanitize the values (f.e. remove HTML tags/entities, replace umlauts, etc.)
     *
     * @param array $extractorsByNodeType
     * @param SearchBucket $targetBucket
     * @return string
     */
    private static function buildMySQLFulltextExtractionForBucket(array $extractorsByNodeType, SearchBucket $targetBucket): string
    {
        $sqlCases = [];

        /** @var array $propertyExtractions */
        foreach ($extractorsByNodeType as $nodeTypeName => $propertyExtractions) {
            $sql = " when nodetypename = '$nodeTypeName' then ";
            $thenSql = [];
            /** @var NodePropertyFulltextExtraction $propertyExtraction */
            foreach ($propertyExtractions as $propertyExtraction) {
                $jsonExtractor = MySQLHelper::extractNormalizedFulltextFromJson('properties', $propertyExtraction->getPropertyName());
                $bucketExtractor = match ($propertyExtraction->getMode()) {
                    FulltextExtractionMode::EXTRACT_INTO_SINGLE_BUCKET => MySQLHelper::extractAllText($jsonExtractor),
                    FulltextExtractionMode::EXTRACT_HTML_TAGS => match ($targetBucket) {
                        SearchBucket::CRITICAL => MySQLHelper::fulltextExtractHtmlTagContents($jsonExtractor, 'h1', 'h2'),
                        SearchBucket::MAJOR => MySQLHelper::fulltextExtractHtmlTagContents($jsonExtractor, 'h3', 'h4', 'h5', 'h6'),
                        SearchBucket::NORMAL, SearchBucket::MINOR => MySQLHelper::fulltextExtractHtmlTextContent($jsonExtractor, 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'),
                    }
                };
                // fallback null to empty string
                $thenSql[] = "coalesce($bucketExtractor, '')";
            }
            if (count($thenSql) === 1) {
                // only one property for node type
                $sql .= $thenSql[0];
            } else if (count($thenSql) > 1) {
                // multiple properties for node type are string concatenated in fulltext extraction
                $sql .= sprintf('concat(%s)', implode(", ' ', ", $thenSql));
            } else {
                // null, in case that no property is configured (this shouldn't happen by regular uses of the API)
                $sql .= 'null';
            }
            $sqlCases[] = $sql;
        }
        if (empty($sqlCases)) {
            return 'null';
        } else {
            $sqlCases[] = ' else null';
            return sprintf("case\n %s\n end", implode("\n", $sqlCases));
        }
    }

    // ## utility functions for document and content node types
    private static function mariaDB_create_functionIsDocumentNodeType(array $documentNodeTypes): string
    {
        $documentNodeTypesCommaSeparated = self::toCommaSeparatedStringList($documentNodeTypes);

        return <<<SQL
            create or replace function sandstorm_kissearch_neos_is_document(
                nodetypename varchar(255)
            ) returns boolean
            begin
                return nodetypename in ($documentNodeTypesCommaSeparated);
            end;
        SQL;
    }

    private static function mariaDB_drop_functionIsDocumentNodeType(): string
    {
        return <<<SQL
            drop function if exists sandstorm_kissearch_neos_is_document;
        SQL;
    }

    private static function mariaDB_create_functionIsContentNodeType(array $contentNodeTypes): string
    {
        $contentNodeTypesCommaSeparated = self::toCommaSeparatedStringList($contentNodeTypes);

        return <<<SQL
            create or replace function sandstorm_kissearch_neos_is_content(
                nodetypename varchar(255)
            ) returns boolean
            begin
                return nodetypename in ($contentNodeTypesCommaSeparated);
            end;
        SQL;
    }

    private static function mariaDB_drop_functionIsContentNodeType(): string
    {
        return <<<SQL
            drop function if exists sandstorm_kissearch_neos_is_content;
        SQL;
    }

    // ## nodes and their documents relation

    private static function mariaDB_create_tableNodesAndTheirDocuments(): string
    {
        return <<<SQL
            create table if not exists sandstorm_kissearch_nodes_and_their_documents (
                relationanchorpoint             bigint         not null,
                contentstreamid                 varbinary(36)  not null,
                node_id                         varchar(64)    not null,
                document_id                     varchar(64)    not null,
                document_title                  longtext,
                document_nodetype               varchar(255)    not null,
                nodetype                        varchar(255)    not null,
                dimensionshash                  varchar(32)     not null,
                dimensionvalues                 json            not null,
                site_nodename                   varchar(255)    not null,
                document_uri_path               varchar(4000)
            );
            -- TODO indices
        SQL;
    }

    private static function mariaDB_drop_tableNodesAndTheirDocuments(): string
    {
        return <<<SQL
            drop table if exists sandstorm_kissearch_nodes_and_their_documents;
        SQL;
    }

    // update function

    private static function mariaDB_create_functionPopulateNodesAndTheirDocuments(): string
    {
        return <<<SQL
            create procedure sandstorm_kissearch_populate_nodes_and_their_documents()
            modifies sql data
            begin
                start transaction;
                truncate table sandstorm_kissearch_nodes_and_their_documents;
                insert into sandstorm_kissearch_nodes_and_their_documents
                    with recursive nodes_and_their_documents as
                                       (select sn.relationanchorpoint,
                                               h.contentstreamid,
                                               sn.nodeaggregateid                         as node_id,
                                               sn.nodetypename                            as nodetype,
                                               h.dimensionspacepointhash                  as dimensionshash,
                                               cast(null as varchar(10000000))            as dimensionvalues,
                                               cast(null as varchar(255))                 as site_nodename,
                                               sn.nodeaggregateid                         as document_id,
                                               json_value(sn.properties, '$.title.value') as document_title,
                                               sn.nodetypename                            as document_nodetype,
                                               (json_value(h.subtreetags, '$.disabled') is null
                                                   or json_value(h.subtreetags, '$.disabled') = false)
                                                                                          as is_not_hidden
                                        from cr_default_p_graph_node sn
                                                 left join cr_default_p_graph_hierarchyrelation h
                                                           on h.childnodeanchor = sn.relationanchorpoint
                                        where sn.nodetypename = 'Neos.Neos:Sites'
                                        union
                                        select cn.relationanchorpoint,
                                               h.contentstreamid,
                                               cn.nodeaggregateid                                             as node_id,
                                               cn.nodetypename                                                as nodetype,
                                               cn.origindimensionspacepointhash                               as dimensionshash,
                                               d.dimensionspacepoint                                          as dimensionvalues,
                                               if(pn.nodetype = 'Neos.Neos:Sites', cn.name, pn.site_nodename) as site_nodename,
                                               if(sandstorm_kissearch_neos_is_document(cn.nodetypename),
                                                  cn.nodeaggregateid,
                                                  pn.document_id)                                             as document_id,
                                               if(sandstorm_kissearch_neos_is_document(cn.nodetypename),
                                                  json_value(cn.properties, '$.title.value'),
                                                  pn.document_title)                                          as document_title,
                                               if(sandstorm_kissearch_neos_is_document(cn.nodetypename),
                                                  cn.nodetypename,
                                                  pn.document_nodetype)                                       as document_nodetype,
                                               (json_value(h.subtreetags, '$.disabled') is null
                                                   or json_value(h.subtreetags, '$.disabled') = false)
                                                                                                              as is_not_hidden
                                        from nodes_and_their_documents pn
                                                 left join cr_default_p_graph_hierarchyrelation h
                                                           on h.parentnodeanchor = pn.relationanchorpoint
                                                               and h.contentstreamid = pn.contentstreamid
                                                 left join cr_default_p_graph_node cn
                                                           on cn.relationanchorpoint = h.childnodeanchor
                                                 left join cr_default_p_graph_dimensionspacepoints d
                                                           on d.hash = cn.origindimensionspacepointhash)
                    select nd.relationanchorpoint,
                           nd.contentstreamid,
                           nd.node_id,
                           nd.document_id,
                           nd.document_title,
                           nd.document_nodetype,
                           nd.nodetype,
                           nd.dimensionshash,
                           nd.dimensionvalues,
                           nd.site_nodename,
                           du.uripath as document_uri_path
                    from nodes_and_their_documents nd
                        left join cr_default_p_neos_documenturipath_uri du
                            on du.nodeaggregateid = nd.node_id
                           and du.dimensionspacepointhash = nd.dimensionshash
                    where nd.site_nodename is not null
                      and nd.is_not_hidden
                      and (sandstorm_kissearch_neos_is_document(nd.nodetype)
                        or sandstorm_kissearch_neos_is_content(nd.nodetype));
                commit;
            end;
        SQL;
    }

    private static function mariaDB_call_functionPopulateNodesAndTheirDocuments(): string
    {
        return <<<SQL
            call sandstorm_kissearch_populate_nodes_and_their_documents();
        SQL;
    }

    private static function mariaDB_drop_functionPopulateNodesAndTheirDocuments(): string
    {
        return <<<SQL
            drop procedure if exists sandstorm_kissearch_populate_nodes_and_their_documents;
        SQL;
    }

    // TODO timed visibility seems to be removed in Neos 9?
    private static function mariaDB_create_functionAnyTimedHidden(): string
    {
        // function for checking hidden before and after datetime for a set of parent nodes, given by array
        return <<<SQL
            create or replace function sandstorm_kissearch_any_timed_hidden(
                timed_hidden json,
                now_time datetime
            ) returns boolean
            begin
                return (
                    select timed_hidden is not null
                        and exists(
                            select 1
                            from json_table (timed_hidden, '$[*]'
                                columns(
                                    hiddenbeforedatetime datetime path '$.before',
                                    hiddenafterdatetime datetime path '$.after'
                                )) as th
                            where
                                case
                                    when th.hiddenbeforedatetime is null then th.hiddenafterdatetime < now_time
                                    when th.hiddenafterdatetime is null then th.hiddenbeforedatetime > now_time
                                    else now_time not between th.hiddenbeforedatetime and th.hiddenafterdatetime
                                end
                        )
                    );
            end;
        SQL;
    }

    private static function mariaDB_create_functionAllDimensionValuesMatch(): string
    {
        return <<<SQL
            create or replace function sandstorm_kissearch_all_dimension_values_match(
                dimension_values_filter longtext,
                dimension_values longtext
            ) returns boolean
            begin
                return (
                    select 1 = all (
                        select
                            json_contains(
                                dimension_values,
                                concat('"', expected_dimension_values.filter_value, '"'),
                                concat('$.', expected_dimension_values.dimension_name, '.', expected_dimension_values.index_key)
                            )
                        from json_table(
                            dimension_values_filter,
                            '$[*]'
                            columns
                            (
                                dimension_name text path '$.dimension_name',
                                index_key text path '$.index_key',
                                filter_value text path '$.filter_value'
                            )
                        ) expected_dimension_values
                    )
                );
            end;
        SQL;
    }

    private static function mariaDB_drop_functionAllDimensionValuesMatch(): string
    {
        return <<<SQL
            drop function if exists sandstorm_kissearch_all_dimension_values_match;
        SQL;
    }

    private static function toCommaSeparatedStringList(array $values): string
    {
        return implode(',',
            array_map(function ($value) {
                return sprintf("'%s'", $value);
            }, $values));
    }

}
