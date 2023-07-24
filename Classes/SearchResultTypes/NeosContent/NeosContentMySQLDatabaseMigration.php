<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseMigrationInterface;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\ColumnNamesByBucket;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\MySQLSearchQueryBuilder;
use Sandstorm\KISSearch\SearchResultTypes\SearchBucket;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeName;

#[Proxy(false)]
class NeosContentMySQLDatabaseMigration implements DatabaseMigrationInterface
{

    private readonly NodeTypesSearchConfiguration $nodeTypeSearchConfiguration;

    // TODO remove hotfix
    private readonly bool $hotfixDisableTimedHiddenBeforeAfter;

    /**
     * @param NodeTypesSearchConfiguration $nodeTypeSearchConfiguration
     * @param bool $hotfixDisableTimedHiddenBeforeAfter
     */
    public function __construct(NodeTypesSearchConfiguration $nodeTypeSearchConfiguration, bool $hotfixDisableTimedHiddenBeforeAfter)
    {
        $this->nodeTypeSearchConfiguration = $nodeTypeSearchConfiguration;
        $this->hotfixDisableTimedHiddenBeforeAfter = $hotfixDisableTimedHiddenBeforeAfter;
    }


    function versionHash(): string
    {
        return $this->nodeTypeSearchConfiguration->buildVersionHash();
    }

    function up(): string
    {
        $columnNameBucketCritical = self::columnNameBucketCritical();
        $columnNameBucketMajor = self::columnNameBucketMajor();
        $columnNameBucketNormal = self::columnNameBucketNormal();
        $columnNameBucketMinor = self::columnNameBucketMinor();

        $fulltextExtractorsByNodeTypeForCritical = self::buildFulltextExtractionForBucket(
            $this->nodeTypeSearchConfiguration->getExtractorsForCritical(), SearchBucket::CRITICAL);
        $fulltextExtractorsByNodeTypeForMajor = self::buildFulltextExtractionForBucket(
            $this->nodeTypeSearchConfiguration->getExtractorsForMajor(), SearchBucket::MAJOR);
        $fulltextExtractorsByNodeTypeForNormal = self::buildFulltextExtractionForBucket(
            $this->nodeTypeSearchConfiguration->getExtractorsForNormal(), SearchBucket::NORMAL);
        $fulltextExtractorsByNodeTypeForMinor = self::buildFulltextExtractionForBucket(
            $this->nodeTypeSearchConfiguration->getExtractorsForMinor(), SearchBucket::MINOR);

        // generated fulltext extraction bucket columns
        $sqlQueries = [
            <<<SQL
                alter table neos_contentrepository_domain_model_nodedata
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
            SQL
        ];

        // fulltext index on generated columns
        $sqlQueries[] = MySQLSearchQueryBuilder::createFulltextIndex(
            SearchResultTypeName::create(NeosContentSearchResultType::TYPE_NAME),
            'neos_contentrepository_domain_model_nodedata',
            new ColumnNamesByBucket(
                critical: [$columnNameBucketCritical],
                major: [$columnNameBucketMajor],
                normal: [$columnNameBucketNormal],
                minor: [$columnNameBucketMinor]
            )
        );

        // view for content node to closest parent document
        $documentNodeTypesCommaSeparated = self::toCommaSeparatedStringList($this->nodeTypeSearchConfiguration->getDocumentNodeTypeNames());
        $contentNodeTypesCommaSeparated = self::toCommaSeparatedStringList($this->nodeTypeSearchConfiguration->getContentNodeTypeNames());
        $sqlQueries[] = <<<SQL
            create or replace view sandstorm_kissearch_nodes_and_their_documents as
            with recursive nodes_and_their_documents as
                   (select n.identifier,
                           n.pathhash,
                           n.parentpathhash,
                           n.nodetype,
                           cast(null as varchar(32))
                               collate utf8mb4_unicode_ci              as dimensionshash,
                           cast(null as varchar(255))
                               collate utf8mb4_unicode_ci              as site_nodename,
                           n.identifier                                as document_id,
                           json_value(n.properties, '$.title')         as document_title,
                           n.nodetype                                  as document_nodetype,
                           n.hidden = 0                                as not_hidden,
                           n.removed = 0                               as not_removed,
                           cast(if(n.hiddenbeforedatetime is not null or n.hiddenafterdatetime is not null,
                                   json_array(json_object(
                                           'before', n.hiddenbeforedatetime,
                                           'after', n.hiddenafterdatetime
                                       )),
                                   json_array()) as varchar(10000000)) as timed_hidden
                    from neos_contentrepository_domain_model_nodedata n
                    where n.path = '/sites'
                      and n.hidden = 0
                      and n.removed = 0
                      and n.workspace = 'live'
                    union
                    select n.identifier,
                           n.pathhash,
                           n.parentpathhash,
                           n.nodetype,
                           n.dimensionshash,
                           if((length(n.path) - length(replace(n.path, '/', ''))) = 2,
                              substring_index(n.path, '/', -1),
                              r.site_nodename)             as site_nodename,
                           if(n.nodetype in ($documentNodeTypesCommaSeparated),
                              n.identifier,
                              r.document_id)               as document_id,
                           if(n.nodetype in ($documentNodeTypesCommaSeparated),
                              json_value(n.properties, '$.title'),
                              r.document_title)            as document_title,
                           if(n.nodetype in ($documentNodeTypesCommaSeparated),
                              n.nodetype,
                              r.document_nodetype)         as document_nodetype,
                           n.hidden = 0 and r.not_hidden   as not_hidden,
                           n.removed = 0 and r.not_removed as not_removed,
                           if(n.hiddenbeforedatetime is not null or n.hiddenafterdatetime is not null,
                              json_array_append(r.timed_hidden, '$', json_object(
                                      'before', n.hiddenbeforedatetime,
                                      'after', n.hiddenafterdatetime
                                  )),
                              r.timed_hidden)              as timed_hidden
                    from neos_contentrepository_domain_model_nodedata n,
                         nodes_and_their_documents r
                    where r.pathhash = n.parentpathhash
                      and n.workspace = 'live'
                      and (r.dimensionshash is null or n.dimensionshash = r.dimensionshash))
            select nd.identifier        as identifier,
                   nd.document_id       as document_id,
                   nd.document_title    as document_title,
                   nd.document_nodetype as document_nodetype,
                   nd.nodetype          as nodetype,
                   nd.dimensionshash    as dimensionshash,
                   nd.site_nodename     as site_nodename,
                   if(json_length(nd.timed_hidden) > 0,
                      nd.timed_hidden,
                      null)             as timed_hidden
            from nodes_and_their_documents nd
            where nd.site_nodename is not null
              and nd.not_hidden
              and nd.not_removed
              and (
                        nd.nodetype in ($documentNodeTypesCommaSeparated)
                    or nd.nodetype in ($contentNodeTypesCommaSeparated)
                );
        SQL;

        // TODO remove hotfix
        $timedCheck = $this->hotfixDisableTimedHiddenBeforeAfter ? '0' :
            <<<SQL
                timed_hidden is not null
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
            SQL;


        // function for checking hidden before and after datetime for a set of parent nodes, given by array
        $sqlQueries[] = <<<SQL
            create or replace function sandstorm_kissearch_any_timed_hidden(
                timed_hidden json,
                now_time datetime
            ) returns boolean
            begin
                return (
                    select $timedCheck
                    );
            end;
        SQL;
        return implode("\n", $sqlQueries);
    }

    private static function toCommaSeparatedStringList(array $values): string
    {
        return implode(',',
            array_map(function ($value) {
                return sprintf("'%s'", $value);
            }, $values));
    }

    private static function buildFulltextExtractionForBucket(array $extractorsByNodeType, SearchBucket $targetBucket): string
    {
        $sqlCases = [];

        /** @var array $propertyExtractions */
        foreach ($extractorsByNodeType as $nodeTypeName => $propertyExtractions) {
            $sql = " when nodetype = '$nodeTypeName' then ";
            $thenSql = [];
            /** @var NodePropertyFulltextExtraction $propertyExtraction */
            foreach ($propertyExtractions as $propertyExtraction) {
                $jsonExtractor = MySQLSearchQueryBuilder::extractNormalizedFulltextFromJson('properties', $propertyExtraction->getPropertyName());
                $thenSql[] = match ($propertyExtraction->getMode()) {
                    FulltextExtractionMode::EXTRACT_INTO_SINGLE_BUCKET => MySQLSearchQueryBuilder::extractAllText($jsonExtractor),
                    FulltextExtractionMode::EXTRACT_HTML_TAGS => match ($targetBucket) {
                        SearchBucket::CRITICAL => MySQLSearchQueryBuilder::fulltextExtractHtmlTagContents($jsonExtractor, 'h1', 'h2'),
                        SearchBucket::MAJOR => MySQLSearchQueryBuilder::fulltextExtractHtmlTagContents($jsonExtractor, 'h3', 'h4', 'h5', 'h6'),
                        SearchBucket::NORMAL, SearchBucket::MINOR => MySQLSearchQueryBuilder::fulltextExtractHtmlTextContent($jsonExtractor, 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'),
                    }
                };
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

    function down(): string
    {
        $columnNameBucketCritical = self::columnNameBucketCritical();
        $columnNameBucketMajor = self::columnNameBucketMajor();
        $columnNameBucketNormal = self::columnNameBucketNormal();
        $columnNameBucketMinor = self::columnNameBucketMinor();

        $sqlQueries = [
            <<<SQL
                alter table neos_contentrepository_domain_model_nodedata
                    drop column if exists $columnNameBucketCritical,
                    drop column if exists $columnNameBucketMajor,
                    drop column if exists $columnNameBucketNormal,
                    drop column if exists $columnNameBucketMinor;
            SQL
        ];

        $sqlQueries[] = MySQLSearchQueryBuilder::dropFulltextIndex(
            SearchResultTypeName::create(NeosContentSearchResultType::TYPE_NAME),
            'neos_contentrepository_domain_model_nodedata'
        );

        $sqlQueries[] = <<<SQL
            drop view if exists sandstorm_kissearch_nodes_and_their_documents;
        SQL;

        $sqlQueries[] = <<<SQL
            drop function if exists sandstorm_kissearch_any_timed_hidden;
        SQL;

        return implode("\n", $sqlQueries);
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
