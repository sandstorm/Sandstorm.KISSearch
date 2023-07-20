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

    /**
     * @param NodeTypesSearchConfiguration $nodeTypeSearchConfiguration
     */
    public function __construct(NodeTypesSearchConfiguration $nodeTypeSearchConfiguration)
    {
        $this->nodeTypeSearchConfiguration = $nodeTypeSearchConfiguration;
    }


    function versionHash(): string
    {
        // TODO implement
        return "TODO";
    }

    function up(): string
    {
        $columnNameBucketCritical = sprintf('search_bucket_%s', SearchBucket::CRITICAL->value);
        $columnNameBucketMajor = sprintf('search_bucket_%s', SearchBucket::MAJOR->value);
        $columnNameBucketNormal = sprintf('search_bucket_%s', SearchBucket::NORMAL->value);
        $columnNameBucketMinor = sprintf('search_bucket_%s', SearchBucket::MINOR->value);

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
            SearchResultTypeName::create(NeosContentSearchResultType::$TYPE_NAME),
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
            create view sandstorm_kissearch_nodes_and_their_documents as
            with recursive nodes_and_their_documents as (select n.identifier,
                                                    n.pathhash,
                                                    n.path,
                                                    n.parentpathhash,
                                                    n.pathhash                          as document_path,
                                                    n.identifier                        as document_id,
                                                    json_value(n.properties, '$.title') as document_title
                                             from neos_contentrepository_domain_model_nodedata n
                                             where n.nodetype in ($documentNodeTypesCommaSeparated)
                                             union
                                             select n.identifier,
                                                    n.pathhash,
                                                    n.path,
                                                    n.parentpathhash,
                                                    r.pathhash       as document_path,
                                                    r.document_id    as document_id,
                                                    r.document_title as document_title
                                             from neos_contentrepository_domain_model_nodedata n,
                                                  nodes_and_their_documents r
                                             where (
                                                         n.nodetype in ($documentNodeTypesCommaSeparated)
                                                     or n.nodetype in ($contentNodeTypesCommaSeparated)
                                                 )
                                               and r.pathhash = n.parentpathhash)
            select identifier, document_id, document_title
            from nodes_and_their_documents
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

    private static function buildFulltextExtractionForBucket(array $extractorsByNodeType, SearchBucket $targetBucket)
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
        $columnNameBucketCritical = sprintf('search_bucket_%s', SearchBucket::CRITICAL->value);
        $columnNameBucketMajor = sprintf('search_bucket_%s', SearchBucket::MAJOR->value);
        $columnNameBucketNormal = sprintf('search_bucket_%s', SearchBucket::NORMAL->value);
        $columnNameBucketMinor = sprintf('search_bucket_%s', SearchBucket::MINOR->value);

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
            SearchResultTypeName::create(NeosContentSearchResultType::$TYPE_NAME),
            'neos_contentrepository_domain_model_nodedata'
        );

        $sqlQueries[] = <<<SQL
            drop view if exists sandstorm_kissearch_nodes_and_their_documents;
        SQL;
        return implode("\n", $sqlQueries);
    }
}
