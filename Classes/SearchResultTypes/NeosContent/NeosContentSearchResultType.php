<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Utility as EelUtility;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\Configuration\ConfigurationManager;
use Sandstorm\KISSearch\Eel\IndexingHelper;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseMigrationInterface;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseType;
use Sandstorm\KISSearch\SearchResultTypes\SearchBucket;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultIdentifier;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeInterface;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeName;
use Sandstorm\KISSearch\SearchResultTypes\UnsupportedDatabaseException;

#[Scope('singleton')]
class NeosContentSearchResultType implements SearchResultTypeInterface
{
    public static string $TYPE_NAME = 'neos_content';

    // injected
    private readonly NodeTypeManager $nodeTypeManager;
    private readonly EelEvaluatorInterface $eelEvaluator;

    private readonly ConfigurationManager $configurationManager;

    /**
     * @param NodeTypeManager $nodeTypeManager
     * @param EelEvaluatorInterface $eelEvaluator
     * @param ConfigurationManager $configurationManager
     */
    public function __construct(NodeTypeManager $nodeTypeManager, EelEvaluatorInterface $eelEvaluator, ConfigurationManager $configurationManager)
    {
        $this->nodeTypeManager = $nodeTypeManager;
        $this->eelEvaluator = $eelEvaluator;
        $this->configurationManager = $configurationManager;
    }

    function getName(): SearchResultTypeName
    {
        return SearchResultTypeName::create(self::$TYPE_NAME);
    }

    function buildUrlToResultPage(SearchResultIdentifier $searchResultIdentifier): string
    {
        // TODO: Implement buildUrlToResultPage() method.
        return "https://google.de";
    }

    function getDatabaseMigration(DatabaseType $databaseType): DatabaseMigrationInterface
    {
        $nodeTypeSearchConfiguration = $this->getFulltextSearchConfiguration();
        return match ($databaseType) {
            DatabaseType::MYSQL => new NeosContentMySQLDatabaseMigration($nodeTypeSearchConfiguration),
            DatabaseType::POSTGRES => throw new \Exception('To be implemented'),
            default => throw new UnsupportedDatabaseException(
                "Neos Content search does not support database of type '$databaseType->name'",
                1689634320
            )
        };
    }

    private function getFulltextSearchConfiguration(): NodeTypesSearchConfiguration
    {
        $excludedNodeTypes = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.KISSearch.excludedNodeTypes'
        );

        // filter excluded node types
        $exclusionFilter = function (NodeType $documentNodeType) use ($excludedNodeTypes) {
            return !in_array($documentNodeType->getName(), $excludedNodeTypes);
        };

        $documentNodeTypes = array_filter(
            $this->nodeTypeManager->getSubNodeTypes('Neos.Neos:Document', false),
            $exclusionFilter
        );

        $contentNodeTypes = array_filter(
            $this->nodeTypeManager->getSubNodeTypes('Neos.Neos:Content', false),
            $exclusionFilter
        );

        $allSearchableNodeTypes = array_merge($documentNodeTypes, $contentNodeTypes);

        $extractorsForCritical = [];
        $extractorsForMajor = [];
        $extractorsForNormal = [];
        $extractorsForMinor = [];
        /** @var NodeType $searchableNodeType */
        foreach ($allSearchableNodeTypes as $searchableNodeType) {
            $nodeTypeName = $searchableNodeType->getName();
            $addExtraction = function(NodePropertyFulltextExtraction $extraction, SearchBucket $targetBucket) use ($nodeTypeName, &$extractorsForCritical, &$extractorsForMajor, &$extractorsForNormal, &$extractorsForMinor) {
                switch ($targetBucket) {
                    case SearchBucket::CRITICAL:
                        self::addExtractionForBucket($nodeTypeName, $extraction, $extractorsForCritical);
                        break;
                    case SearchBucket::MAJOR:
                        self::addExtractionForBucket($nodeTypeName, $extraction, $extractorsForMajor);
                        break;
                    case SearchBucket::NORMAL:
                        self::addExtractionForBucket($nodeTypeName, $extraction, $extractorsForNormal);
                        break;
                    case SearchBucket::MINOR:
                        self::addExtractionForBucket($nodeTypeName, $extraction, $extractorsForMinor);
                }
            };

            foreach ($searchableNodeType->getProperties() as $propertyName => $propertyConfiguration) {
                $searchConfiguration = array_key_exists('search', $propertyConfiguration) ? $propertyConfiguration['search'] : [];
                if (empty($searchConfiguration)) {
                    continue;
                }

                // new configuration format
                $bucketConfiguration = array_key_exists('bucket', $searchConfiguration) ? $searchConfiguration['bucket'] : null;
                $extractHtmlIntoConfiguration = array_key_exists('extractHtmlInto', $searchConfiguration) ? $searchConfiguration['extractHtmlInto'] : null;
                // backwards compatibility to SearchPlugin
                $fulltextExtractorConfiguration = array_key_exists('fulltextExtractor', $searchConfiguration) ? $searchConfiguration['fulltextExtractor'] : null;

                // validate configuration, only one key must be specified
                if ($bucketConfiguration !== null && $extractHtmlIntoConfiguration !== null) {
                    throw new InvalidNodeTypeSearchConfigurationException(
                        "Property '$propertyName' of node type '$nodeTypeName' has invalid search configuration; only one of 'bucket' or 'extractHtmlInto' must be set.",
                        1689765367
                    );
                }

                if ($bucketConfiguration !== null) {
                    $targetBucket = SearchBucket::from($bucketConfiguration);
                    $extraction = new NodePropertyFulltextExtraction($propertyName, FulltextExtractionMode::EXTRACT_INTO_SINGLE_BUCKET);
                    $addExtraction($extraction, $targetBucket);
                } else if ($extractHtmlIntoConfiguration !== null) {
                    $targetBuckets = null;
                    if ($extractHtmlIntoConfiguration === 'all') {
                        // all shortcut
                        $targetBuckets = SearchBucket::allBuckets();
                    } else if (is_array($extractHtmlIntoConfiguration)) {
                        // specific set of buckets
                        $targetBuckets = array_map(function(string $bucketConfiguration) {
                            return SearchBucket::from($bucketConfiguration);
                        }, $extractHtmlIntoConfiguration);
                    } else {
                        // single target bucket
                        $targetBuckets = [SearchBucket::from($extractHtmlIntoConfiguration)];
                    }
                    $extraction = new NodePropertyFulltextExtraction($propertyName, FulltextExtractionMode::EXTRACT_HTML_TAGS);
                    foreach ($targetBuckets as $targetBucket) {
                        $addExtraction($extraction, $targetBucket);
                    }
                } else if ($fulltextExtractorConfiguration !== null) {
                    $evaluated = $this->evaluateEelExpression($fulltextExtractorConfiguration, $propertyName);
                    if ($evaluated instanceof FulltextExtractionInstruction) {
                        $extraction = new NodePropertyFulltextExtraction($propertyName, $evaluated->getMode());
                        foreach ($evaluated->getTargetBuckets() as $targetBucket) {
                            $addExtraction($extraction, $targetBucket);
                        }
                    } else {
                        // TODO how to handle that? throw exception, log or ignore silently?
                    }
                }
            }
        }

        return new NodeTypesSearchConfiguration(
            array_map(function (NodeType $documentNodeType) {
                return $documentNodeType->getName();
            }, $documentNodeTypes),
            $extractorsForCritical,
            $extractorsForMajor,
            $extractorsForNormal,
            $extractorsForMinor
        );
    }

    private function addExtractionForBucket(string $nodeTypeName, NodePropertyFulltextExtraction $extraction, &$bucketConfiguration): void
    {
        if (!array_key_exists($nodeTypeName, $bucketConfiguration)) {
            $bucketConfiguration[$nodeTypeName] = [];
        }
        $bucketConfiguration[$nodeTypeName][] = $extraction;
    }

    private function evaluateEelExpression(string $expression, string $propertyName): mixed
    {
        $defaultContext = EelUtility::getDefaultContextVariables([
            'Indexing' => IndexingHelper::class
        ]);

        $contextVariables = array_merge($defaultContext, [
            'propertyName' => $propertyName
        ]);

        // TODO try catch error handling here
        return EelUtility::evaluateEelExpression($expression, $this->eelEvaluator, $contextVariables);
    }

    function getResultSearchingQueryPart(DatabaseType $databaseType): string
    {
        // TODO: Implement getResultSearchingQueryPart() method.
        return "";
    }

    function getResultMergingQueryPart(DatabaseType $databaseType): string
    {
        // TODO: Implement getResultMergingQueryPart() method.
        return "";
    }


}
