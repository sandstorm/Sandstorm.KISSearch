<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Schema;

use Neos\Flow\Annotations\Inject;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\DBAbstraction\MySQLHelper;
use Sandstorm\KISSearch\Api\Schema\SearchDependencyRefresherInterface;
use Sandstorm\KISSearch\Api\Schema\SearchSchemaInterface;
use Sandstorm\KISSearch\Flow\InvalidConfigurationException;
use Sandstorm\KISSearch\Neos\NeosContentSearchResultType;
use Sandstorm\KISSearch\Neos\Schema\Model\FulltextExtractionMode;
use Sandstorm\KISSearch\Neos\Schema\Model\NodePropertyFulltextExtraction;
use Sandstorm\KISSearch\Neos\Schema\Model\NodeTypesSearchConfiguration;
use Sandstorm\KISSearch\Neos\SearchBucket;

/**
 * Creates (and drops) the KISSearch DB Schema.
 *
 * There is no point in this class being un-proxied, since is has complex dependencies to the NodeTypeManager etc.
 * You probably won't use this class without booting Flow anyways. Also, there is no need for performance optimization
 * during schema creation.
 */
#[Scope('singleton')]
class NeosContentSearchSchema implements SearchSchemaInterface, SearchDependencyRefresherInterface
{

    private const OPTION_CONTENT_REPOSITORY = 'contentRepository';
    private const OPTION_EXCLUDED_NODE_TYPES = 'excludedNodeTypes';
    private const OPTION_BASE_DOCUMENT_NODE_TYPE = 'baseDocumentNodeType';
    private const OPTION_BASE_CONTENT_NODE_TYPE = 'baseContentNodeType';

    private const OPTION_DEFAULT_BASE_DOCUMENT_NODE_TYPE = 'Neos.Neos:Document';
    private const OPTION_DEFAULT_BASE_CONTENT_NODE_TYPE = 'Neos.Neos:Content';

    #[Inject]
    protected NodeTypesSearchConfigurationProvider $schemaConfiguration;

    protected ?NodeTypesSearchConfiguration $nodeTypesSearchConfiguration = null;

    public static function createInstance(NodeTypesSearchConfiguration $nodeTypesSearchConfiguration): self
    {
        $instance = new NeosContentSearchSchema();
        $instance->nodeTypesSearchConfiguration = $nodeTypesSearchConfiguration;
        return $instance;
    }

    private static function getContentRepositoryIdFromOptions(string $schemaIdentifier, array $options): string
    {
        $contentRepositoryId = $options[self::OPTION_CONTENT_REPOSITORY] ??
            throw new InvalidConfigurationException(
                sprintf("No '%s' option set in schema configuration options.", self::OPTION_CONTENT_REPOSITORY)
            );
        if (!is_string($contentRepositoryId) || strlen(trim($contentRepositoryId)) === 0) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid search schema configuration '...schemas.%s.options.%s'; value must be a non-empty string but was: %s",
                    $schemaIdentifier,
                    self::OPTION_CONTENT_REPOSITORY,
                    gettype($contentRepositoryId)
                )
            );
        }
        return $contentRepositoryId;
    }

    private static function getExcludedNodeTypesFromOptions(string $schemaIdentifier, array $options): array
    {
        $value = $options[self::OPTION_EXCLUDED_NODE_TYPES] ?? [];
        if (!is_array($value)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid search schema configuration '...schemas.%s.options.%s'; value must be an array but was: %s",
                    $schemaIdentifier,
                    self::OPTION_EXCLUDED_NODE_TYPES,
                    gettype($value)
                )
            );
        }
        return $value;
    }

    private static function getBaseDocumentNodeTypeFromOptions(string $schemaIdentifier, array $options): string
    {
        $value = $options[self::OPTION_BASE_DOCUMENT_NODE_TYPE] ?? self::OPTION_DEFAULT_BASE_DOCUMENT_NODE_TYPE;
        if (!is_string($value) || strlen(trim($value)) === 0) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid search schema configuration '...schemas.%s.options.%s'; value must be a non-empty string but was: %s",
                    $schemaIdentifier,
                    self::OPTION_BASE_DOCUMENT_NODE_TYPE,
                    gettype($value)
                )
            );
        }
        return $value;
    }

    private static function getBaseContentNodeTypeFromOptions(string $schemaIdentifier, array $options): string
    {
        $value = $options[self::OPTION_BASE_CONTENT_NODE_TYPE] ?? self::OPTION_DEFAULT_BASE_CONTENT_NODE_TYPE;
        if (!is_string($value) || strlen(trim($value)) === 0) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid search schema configuration '...schemas.%s.options.%s'; value must be a non-empty string but was: %s",
                    $schemaIdentifier,
                    self::OPTION_BASE_CONTENT_NODE_TYPE,
                    gettype($value)
                )
            );
        }
        return $value;
    }

    function createSchema(DatabaseType $databaseType, string $schemaIdentifier, array $options): array
    {
        $contentRepositoryId = self::getContentRepositoryIdFromOptions($schemaIdentifier, $options);
        $excludedNodeTypes = self::getExcludedNodeTypesFromOptions($schemaIdentifier, $options);
        $baseDocumentNodeType = self::getBaseDocumentNodeTypeFromOptions($schemaIdentifier, $options);
        $baseContentNodeType = self::getBaseContentNodeTypeFromOptions($schemaIdentifier, $options);

        // TODO postgres

        // ether explicitly set or calculated from the NodeTypeManager
        if ($this->nodeTypesSearchConfiguration === null) {
            $this->nodeTypesSearchConfiguration = $this->schemaConfiguration->getNodeTypesSearchConfiguration(
                $contentRepositoryId,
                $excludedNodeTypes,
                $baseDocumentNodeType,
                $baseContentNodeType
            );
        }

        return [
            self::mariaDB_create_generatedSearchBucketColumns(
                $this->nodeTypesSearchConfiguration->getExtractorsForCritical(),
                $this->nodeTypesSearchConfiguration->getExtractorsForMajor(),
                $this->nodeTypesSearchConfiguration->getExtractorsForNormal(),
                $this->nodeTypesSearchConfiguration->getExtractorsForMinor(),
                $contentRepositoryId
            ),
            self::mariaDB_create_functionGetSupertypesOfNodeType(
                $contentRepositoryId,
                $this->nodeTypesSearchConfiguration->getNodeTypeInheritance()
            ),
            self::mariaDB_create_tableNodesAndTheirDocuments($contentRepositoryId),
            self::mariaDB_create_functionIsDocumentNodeType(
                $contentRepositoryId,
                $this->nodeTypesSearchConfiguration->getDocumentNodeTypeNames()
            ),
            self::mariaDB_create_functionIsContentNodeType(
                $contentRepositoryId,
                $this->nodeTypesSearchConfiguration->getContentNodeTypeNames()
            ),
            self::mariaDB_create_functionPopulateNodesAndTheirDocuments($contentRepositoryId),
            self::mariaDB_create_functionAllDimensionValuesMatch(),
            // move to data update command
            //self::mariaDB_call_functionPopulateNodesAndTheirDocuments()
        ];
    }

    function dropSchema(DatabaseType $databaseType, string $schemaIdentifier, array $options): array
    {
        $contentRepositoryId = self::getContentRepositoryIdFromOptions($schemaIdentifier, $options);

        // TODO postgres
        return [
            self::mariaDB_drop_generatedSearchBucketColumns($contentRepositoryId),
            self::mariaDB_drop_tableNodesAndTheirDocuments($contentRepositoryId),
            self::mariaDB_drop_functionIsDocumentNodeType($contentRepositoryId),
            self::mariaDB_drop_functionIsContentNodeType($contentRepositoryId),
            self::mariaDB_drop_functionPopulateNodesAndTheirDocuments($contentRepositoryId),
            self::mariaDB_drop_functionAllDimensionValuesMatch(),
            self::mariaDB_drop_functionGetSupertypesOfNodeType($contentRepositoryId)
        ];
    }

    function refreshSearchDependencies(DatabaseType $databaseType, string $schemaIdentifier, array $options): array
    {
        $contentRepositoryId = self::getContentRepositoryIdFromOptions($schemaIdentifier, $options);

        return [
            self::mariaDB_call_functionPopulateNodesAndTheirDocuments($contentRepositoryId)
        ];
    }

    // ## generated search bucket columns

    private static function mariaDB_create_generatedSearchBucketColumns(
        array $extractorsForCritical,
        array $extractorsForMajor,
        array $extractorsForNormal,
        array $extractorsForMinor,
        string $contentRepositoryId
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

        $tableName = NeosContentSearchResultType::buildCRTableName_nodes($contentRepositoryId);

        return <<<SQL
                alter table $tableName
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
                create fulltext index idx_neos_content_all_buckets on $tableName ($columnNameBucketCritical, $columnNameBucketMajor, $columnNameBucketNormal, $columnNameBucketMinor);
                create fulltext index idx_neos_content_bucket_critical on $tableName ($columnNameBucketCritical);
                create fulltext index idx_neos_content_bucket_major on $tableName ($columnNameBucketMajor);
                create fulltext index idx_neos_content_bucket_normal on $tableName ($columnNameBucketNormal);
                create fulltext index idx_neos_content_bucket_minor on $tableName ($columnNameBucketMinor);
        SQL;
    }

    private static function mariaDB_drop_generatedSearchBucketColumns(string $contentRepositoryId): string
    {
        $columnNameBucketCritical = NeosContentSearchResultType::BUCKET_COLUMN_CRITICAL;
        $columnNameBucketMajor = NeosContentSearchResultType::BUCKET_COLUMN_MAJOR;
        $columnNameBucketNormal = NeosContentSearchResultType::BUCKET_COLUMN_NORMAL;
        $columnNameBucketMinor = NeosContentSearchResultType::BUCKET_COLUMN_MINOR;

        $tableName = NeosContentSearchResultType::buildCRTableName_nodes($contentRepositoryId);

        return <<<SQL
            drop index if exists idx_neos_content_all_buckets on $tableName;
            drop index if exists idx_neos_content_bucket_critical on $tableName;
            drop index if exists idx_neos_content_bucket_major on $tableName;
            drop index if exists idx_neos_content_bucket_normal on $tableName;
            drop index if exists idx_neos_content_bucket_minor on $tableName;
            alter table $tableName
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
    private static function buildMySQLFulltextExtractionForBucket(
        array $extractorsByNodeType,
        SearchBucket $targetBucket
    ): string {
        $sqlCases = [];

        /** @var array $propertyExtractions */
        foreach ($extractorsByNodeType as $nodeTypeName => $propertyExtractions) {
            $sql = " when nodetypename = '$nodeTypeName' then ";
            $thenSql = [];
            /** @var NodePropertyFulltextExtraction $propertyExtraction */
            foreach ($propertyExtractions as $propertyExtraction) {
                $jsonExtractor = MySQLHelper::extractNormalizedFulltextFromJson(
                    'properties',
                    $propertyExtraction->getPropertyName()
                );
                $bucketExtractor = match ($propertyExtraction->getMode()) {
                    FulltextExtractionMode::EXTRACT_INTO_SINGLE_BUCKET => MySQLHelper::extractAllText($jsonExtractor),
                    FulltextExtractionMode::EXTRACT_HTML_TAGS => match ($targetBucket) {
                        SearchBucket::CRITICAL => MySQLHelper::fulltextExtractHtmlTagContents(
                            $jsonExtractor,
                            'h1',
                            'h2'
                        ),
                        SearchBucket::MAJOR => MySQLHelper::fulltextExtractHtmlTagContents(
                            $jsonExtractor,
                            'h3',
                            'h4',
                            'h5',
                            'h6'
                        ),
                        SearchBucket::NORMAL, SearchBucket::MINOR => MySQLHelper::fulltextExtractHtmlTextContent(
                            $jsonExtractor,
                            'h1',
                            'h2',
                            'h3',
                            'h4',
                            'h5',
                            'h6'
                        ),
                    }
                };
                // fallback null to empty string
                $thenSql[] = "coalesce($bucketExtractor, '')";
            }
            if (count($thenSql) === 1) {
                // only one property for node type
                $sql .= $thenSql[0];
            } else {
                if (count($thenSql) > 1) {
                    // multiple properties for node type are string concatenated in fulltext extraction
                    $sql .= sprintf('concat(%s)', implode(", ' ', ", $thenSql));
                } else {
                    // null, in case that no property is configured (this shouldn't happen by regular uses of the API)
                    $sql .= 'null';
                }
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
    private static function mariaDB_create_functionIsDocumentNodeType(string $contentRepositoryId, array $documentNodeTypes): string
    {
        $documentNodeTypesCommaSeparated = self::toCommaSeparatedStringList($documentNodeTypes);

        return <<<SQL
            create or replace function sandstorm_kissearch_neos_is_document_$contentRepositoryId(
                nodetypename varchar(255)
            ) returns boolean
            begin
                return nodetypename in ($documentNodeTypesCommaSeparated);
            end;
        SQL;
    }

    private static function mariaDB_drop_functionIsDocumentNodeType(string $contentRepositoryId): string
    {
        return <<<SQL
            drop function if exists sandstorm_kissearch_neos_is_document_$contentRepositoryId;
        SQL;
    }

    private static function mariaDB_create_functionIsContentNodeType(string $contentRepositoryId, array $contentNodeTypes): string
    {
        $contentNodeTypesCommaSeparated = self::toCommaSeparatedStringList($contentNodeTypes);

        return <<<SQL
            create or replace function sandstorm_kissearch_neos_is_content_$contentRepositoryId(
                nodetypename varchar(255)
            ) returns boolean
            begin
                return nodetypename in ($contentNodeTypesCommaSeparated);
            end;
        SQL;
    }

    private static function mariaDB_drop_functionIsContentNodeType(string $contentRepositoryId): string
    {
        return <<<SQL
            drop function if exists sandstorm_kissearch_neos_is_content_$contentRepositoryId;
        SQL;
    }

    private static function mariaDB_create_functionGetSupertypesOfNodeType(string $contentRepositoryId, array $nodeTypeInheritance): string
    {
        $cases = [];
        foreach ($nodeTypeInheritance as $nodeType => $supertypes) {
            $commaSeparatedSupertypes = self::toCommaSeparatedStringList($supertypes);
            $cases[] = <<<SQL
                when nodetypename = '$nodeType' then json_array($commaSeparatedSupertypes)
            SQL;
        }
        $casesSql = implode("\n", $cases);

        return <<<SQL
            create or replace function sandstorm_kissearch_get_super_types_of_nodetype_$contentRepositoryId(
                nodetypename varchar(255)
            ) returns json
            begin
                return case
                    $casesSql
                end;
            end;
        SQL;
    }

    private static function mariaDB_drop_functionGetSupertypesOfNodeType(string $contentRepositoryId): string
    {
        return <<<SQL
            drop function if exists sandstorm_kissearch_get_super_types_of_nodetype_$contentRepositoryId;
        SQL;
    }

    // ## nodes and their documents relation

    private static function mariaDB_create_tableNodesAndTheirDocuments(string $contentRepositoryId): string
    {
        return <<<SQL
            create table if not exists sandstorm_kissearch_nodes_and_their_documents_$contentRepositoryId (
                relationanchorpoint             bigint         not null,
                contentstreamid                 varbinary(36)  not null,
                workspace_name                  varchar(36)    not null,
                node_id                         varchar(64)    not null,
                document_id                     varchar(64)    not null,
                document_title                  longtext,
                document_nodetype               varchar(255)    not null,
                inherited_document_nodetypes    json            not null,
                nodetype                        varchar(255)    not null,
                inherited_nodetypes             json            not null,
                dimensionshash                  varchar(32)     not null,
                dimensionvalues                 json            not null,
                site_nodename                   varchar(255)    not null,
                document_uri_path               varchar(4000),
                parent_documents                json            not null
            );
            -- TODO indices
        SQL;
    }

    private static function mariaDB_drop_tableNodesAndTheirDocuments(string $contentRepositoryId): string
    {
        return <<<SQL
            drop table if exists sandstorm_kissearch_nodes_and_their_documents_$contentRepositoryId;
        SQL;
    }

    // update function

    private static function mariaDB_create_functionPopulateNodesAndTheirDocuments(string $contentRepositoryId): string
    {
        $tableNameNode = NeosContentSearchResultType::buildCRTableName_nodes($contentRepositoryId);
        $tableNameGraphHierarchy = NeosContentSearchResultType::buildCRTableName_graphHierarchy($contentRepositoryId);
        $tableNameDimensionSpacePoint = NeosContentSearchResultType::buildCRTableName_dimensionSpacePoints(
            $contentRepositoryId
        );
        $tableNameDocumentUriPath = NeosContentSearchResultType::buildCRTableName_documentUriPath($contentRepositoryId);
        $tableNameWorkspace = NeosContentSearchResultType::buildCRTableName_workspace($contentRepositoryId);

        return <<<SQL
            create procedure sandstorm_kissearch_populate_nodes_and_their_documents_$contentRepositoryId()
            modifies sql data
            begin
                start transaction;
                truncate table sandstorm_kissearch_nodes_and_their_documents_$contentRepositoryId;
                insert into sandstorm_kissearch_nodes_and_their_documents_$contentRepositoryId
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
                                                                                          as is_not_hidden,
                                               cast('[]' as varchar(10000000))            as parent_documents
                                        from $tableNameNode sn
                                                 left join $tableNameGraphHierarchy h
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
                                               if(sandstorm_kissearch_neos_is_document_$contentRepositoryId(cn.nodetypename),
                                                  cn.nodeaggregateid,
                                                  pn.document_id)                                             as document_id,
                                               if(sandstorm_kissearch_neos_is_document_$contentRepositoryId(cn.nodetypename),
                                                  json_value(cn.properties, '$.title.value'),
                                                  pn.document_title)                                          as document_title,
                                               if(sandstorm_kissearch_neos_is_document_$contentRepositoryId(cn.nodetypename),
                                                  cn.nodetypename,
                                                  pn.document_nodetype)                                       as document_nodetype,
                                               (json_value(h.subtreetags, '$.disabled') is null
                                                   or json_value(h.subtreetags, '$.disabled') = false)
                                                                                                              as is_not_hidden,
                                               if(sandstorm_kissearch_neos_is_document_$contentRepositoryId(cn.nodetypename),
                                                  json_array_append(pn.parent_documents, '$', cn.nodeaggregateid),
                                                  pn.parent_documents
                                               )                                                              as parent_documents
                                        from nodes_and_their_documents pn
                                                 left join $tableNameGraphHierarchy h
                                                           on h.parentnodeanchor = pn.relationanchorpoint
                                                               and h.contentstreamid = pn.contentstreamid
                                                 left join $tableNameNode cn
                                                           on cn.relationanchorpoint = h.childnodeanchor
                                                 left join $tableNameDimensionSpacePoint d
                                                           on d.hash = cn.origindimensionspacepointhash)
                    select nd.relationanchorpoint,
                           nd.contentstreamid,
                           ws.name as workspace_name,
                           nd.node_id,
                           nd.document_id,
                           nd.document_title,
                           nd.document_nodetype,
                           sandstorm_kissearch_get_super_types_of_nodetype_$contentRepositoryId(nd.document_nodetype) as inherited_document_nodetypes,
                           nd.nodetype,
                           sandstorm_kissearch_get_super_types_of_nodetype_$contentRepositoryId(nd.nodetype) as inherited_nodetypes,
                           nd.dimensionshash,
                           nd.dimensionvalues,
                           nd.site_nodename,
                           du.uripath as document_uri_path,
                           nd.parent_documents
                    from nodes_and_their_documents nd
                        left join $tableNameDocumentUriPath du
                            on du.nodeaggregateid = nd.document_id
                           and du.dimensionspacepointhash = nd.dimensionshash
                        left join $tableNameWorkspace ws
                            on ws.currentContentStreamId = nd.contentstreamid
                    where nd.site_nodename is not null
                      and nd.is_not_hidden
                      and (sandstorm_kissearch_neos_is_document_$contentRepositoryId(nd.nodetype)
                        or sandstorm_kissearch_neos_is_content_$contentRepositoryId(nd.nodetype));
                commit;
            end;
        SQL;
    }

    public static function mariaDB_call_functionPopulateNodesAndTheirDocuments(string $contentRepositoryId): string
    {
        return <<<SQL
            call sandstorm_kissearch_populate_nodes_and_their_documents_$contentRepositoryId();
        SQL;
    }

    private static function mariaDB_drop_functionPopulateNodesAndTheirDocuments(string $contentRepositoryId): string
    {
        return <<<SQL
            drop procedure if exists sandstorm_kissearch_populate_nodes_and_their_documents_$contentRepositoryId;
        SQL;
    }

    // TODO timed visibility seems to be removed in Neos 9?
    /*
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
    */

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
                                concat('$.', expected_dimension_values.dimension_name)
                            )
                        from json_table(
                            dimension_values_filter,
                            '$[*]'
                            columns
                            (
                                dimension_name text path '$.dimension_name',
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
        return implode(
            ',',
            array_map(function ($value) {
                return sprintf("'%s'", $value);
            }, $values)
        );
    }

}
