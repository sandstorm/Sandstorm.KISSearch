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
        return implode("\n", $sqlQueries);
    }

    private static function buildFulltextExtractionForBucket(array $extractorsByNodeType, SearchBucket $targetBucket) {
        $sqlCases = [];

        /** @var array $propertyExtractions */
        foreach ($extractorsByNodeType as $nodeTypeName => $propertyExtractions) {
            $sql = " when nodetype = '$nodeTypeName' then ";
            $thenSql = [];
            /** @var NodePropertyFulltextExtraction $propertyExtraction */
            foreach ($propertyExtractions as $propertyExtraction) {
                $jsonExtractor = MySQLSearchQueryBuilder::extractNormalizedFulltextFromJson('properties', $propertyExtraction->getPropertyName());
                $thenSql[] = match($propertyExtraction->getMode()) {
                    FulltextExtractionMode::EXTRACT_INTO_SINGLE_BUCKET => MySQLSearchQueryBuilder::extractAllText($jsonExtractor),
                    FulltextExtractionMode::EXTRACT_HTML_TAGS => match($targetBucket) {
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

        return implode("\n", $sqlQueries);
    }
}
