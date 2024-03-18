<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Utility as EelUtility;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Neos\Domain\Model\Site;
use Sandstorm\KISSearch\Eel\IndexingHelper;
use Sandstorm\KISSearch\InvalidConfigurationException;
use Sandstorm\KISSearch\PostgresTS\PostgresFulltextSearchConfiguration;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseMigrationInterface;
use Sandstorm\KISSearch\SearchResultTypes\DatabaseType;
use Sandstorm\KISSearch\SearchResultTypes\SearchBucket;
use Sandstorm\KISSearch\SearchResultTypes\SearchQueryProviderInterface;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeInterface;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeName;
use Sandstorm\KISSearch\SearchResultTypes\UnsupportedDatabaseException;

#[Scope('singleton')]
class NeosContentSearchResultType implements SearchResultTypeInterface
{
    public const TYPE_NAME = 'neos_content';

    // injected
    private readonly NodeTypeManager $nodeTypeManager;
    private readonly EelEvaluatorInterface $eelEvaluator;

    private readonly ConfigurationManager $configurationManager;

    private readonly NeosDocumentUrlGenerator $documentUrlGenerator;

    private readonly EntityManagerInterface $entityManager;

    private bool $requiresReindexOfNodesToTheirDocuments = false;

    /**
     * @param NodeTypeManager $nodeTypeManager
     * @param EelEvaluatorInterface $eelEvaluator
     * @param ConfigurationManager $configurationManager
     * @param NeosDocumentUrlGenerator $documentUrlGenerator
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(NodeTypeManager $nodeTypeManager, EelEvaluatorInterface $eelEvaluator, ConfigurationManager $configurationManager, NeosDocumentUrlGenerator $documentUrlGenerator, EntityManagerInterface $entityManager)
    {
        $this->nodeTypeManager = $nodeTypeManager;
        $this->eelEvaluator = $eelEvaluator;
        $this->configurationManager = $configurationManager;
        $this->documentUrlGenerator = $documentUrlGenerator;
        $this->entityManager = $entityManager;
    }

    public static function name(): SearchResultTypeName
    {
        return SearchResultTypeName::create(self::TYPE_NAME);
    }

    public function getName(): SearchResultTypeName
    {
        return self::name();
    }

    public function buildUrlToResultPage(SearchResult $searchResult): ?string
    {
        return $this->documentUrlGenerator->forSearchResult($searchResult);
    }

    /**
     * @param DatabaseType $databaseType
     * @return DatabaseMigrationInterface
     * @throws InvalidConfigurationTypeException
     */
    public function getDatabaseMigration(DatabaseType $databaseType): DatabaseMigrationInterface
    {
        $nodeTypeSearchConfiguration = $this->getFulltextSearchConfiguration();

        // TODO remove hotfix
        $hotfixDisableTimedHiddenBeforeAfter = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.KISSearch.hotfixDisableTimedHiddenBeforeAfter'
        );

        return match ($databaseType) {
            DatabaseType::MYSQL, DatabaseType::MARIADB => new NeosContentMySQLDatabaseMigration(
                $nodeTypeSearchConfiguration,
                $hotfixDisableTimedHiddenBeforeAfter
            ),
            DatabaseType::POSTGRES => new NeosContentPostgresDatabaseMigration(
                $nodeTypeSearchConfiguration,
                PostgresFulltextSearchConfiguration::fromSettings($this->configurationManager)
            ),
            default => throw new UnsupportedDatabaseException(
                "Neos Content search does not support database of type '$databaseType->name'",
                1689634320
            )
        };
    }

    private function getFulltextSearchConfiguration(): NodeTypesSearchConfiguration
    {
        $excludedNodeTypes = $this->getExcludedNodeTypesConfiguration();
        $baseDocumentNodeType = $this->getBaseDocumentNodeTypeConfiguration();
        $baseContentNodeType = $this->getBaseContentNodeTypeConfiguration();

        // filter excluded node types
        $exclusionFilter = function (NodeType $documentNodeType) use ($excludedNodeTypes) {
            foreach ($excludedNodeTypes as $excluded) {
                if ($documentNodeType->isOfType($excluded)) {
                    return false;
                }
            }
            return true;
        };

        $documentNodeTypes = array_filter(
            $this->nodeTypeManager->getSubNodeTypes($baseDocumentNodeType, false),
            $exclusionFilter
        );

        $contentNodeTypes = array_filter(
            $this->nodeTypeManager->getSubNodeTypes($baseContentNodeType, false),
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
            $addExtraction = function (NodePropertyFulltextExtraction $extraction, SearchBucket $targetBucket) use ($nodeTypeName, &$extractorsForCritical, &$extractorsForMajor, &$extractorsForNormal, &$extractorsForMinor) {
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
                        $targetBuckets = array_map(function (string $bucketConfiguration) {
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
            array_map(function (NodeType $contentNodeType) {
                return $contentNodeType->getName();
            }, $contentNodeTypes),
            $extractorsForCritical,
            $extractorsForMajor,
            $extractorsForNormal,
            $extractorsForMinor
        );
    }

    private function getExcludedNodeTypesConfiguration(): array
    {
        $excludedNodeTypes = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.KISSearch.neosContent.excludedNodeTypes'
        );
        if ($excludedNodeTypes === null) {
            $excludedNodeTypes = [];
        }
        if (!is_array($excludedNodeTypes)) {
            throw new InvalidConfigurationException(
                sprintf("Configuration 'Sandstorm.KISSearch.neosContent.excludedNodeTypes' must be an array; but was: %s", gettype($excludedNodeTypes)),
                1690070843
            );
        }
        return $excludedNodeTypes;
    }

    private function getBaseDocumentNodeTypeConfiguration(): string
    {
        return $this->getValidNodeTypeConfiguration(
            'Sandstorm.KISSearch.neosContent.baseDocumentNodeType',
            'Neos.Neos:Document'
        );
    }

    private function getBaseContentNodeTypeConfiguration(): string
    {
        return $this->getValidNodeTypeConfiguration(
            'Sandstorm.KISSearch.neosContent.baseContentNodeType',
            'Neos.Neos:Content'
        );
    }

    private function getValidNodeTypeConfiguration(string $configurationPath, string $defaultBaseNodeType): string
    {
        $baseNodeType = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            $configurationPath
        );
        if ($baseNodeType === null) {
            return $defaultBaseNodeType;
        }
        if (!is_string($baseNodeType)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Configuration '%s' must be a string; but was: %s",
                    $configurationPath,
                    gettype($baseNodeType)
                ),
                1690071148
            );
        }
        // check if configured node type exists
        if (!$this->nodeTypeManager->hasNodeType($baseNodeType)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid configuration '%s'; node type '%s' does not exist",
                    $configurationPath,
                    $baseNodeType
                ),
                1690071560
            );
        }
        // check if configured node type extends or is equal to 'Neos.Neos:Document'
        $nodeType = $this->nodeTypeManager->getNodeType($baseNodeType);
        if (!$nodeType->isOfType($defaultBaseNodeType)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Configuration '%s' must extend " .
                    "or be of type '%s'; but was: %s",
                    $configurationPath,
                    $defaultBaseNodeType,
                    $baseNodeType
                ),
                1690071423
            );
        }
        return $baseNodeType;
    }

    private function addExtractionForBucket(string $nodeTypeName, NodePropertyFulltextExtraction $extraction, &$bucketConfiguration): void
    {
        if (!array_key_exists($nodeTypeName, $bucketConfiguration)) {
            $bucketConfiguration[$nodeTypeName] = [];
        }
        $bucketConfiguration[$nodeTypeName][$extraction->getPropertyName()] = $extraction;
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

    public function getSearchQueryProvider(DatabaseType $databaseType): SearchQueryProviderInterface
    {
        return match ($databaseType) {
            DatabaseType::MYSQL, DatabaseType::MARIADB => new NeosContentMySQLSearchQueryProvider(),
            DatabaseType::POSTGRES => new NeosContentPostgresSearchQueryProvider(),
            default => throw new UnsupportedDatabaseException(
                "Neos Content search does not support database of type '$databaseType->name'",
                1689934246
            )
        };
    }

    /**
     * Called via AOP Signal Slot. See Package class.
     *
     * @param NodeInterface $node
     * @param Workspace $targetWorkspace
     * @return void
     */
    public function onNodePublished(NodeInterface $node, Workspace $targetWorkspace): void
    {
        if ($this->requiresReindexOfNodesToTheirDocuments === true) {
            // already re-indexing
            return;
        }
        if ($targetWorkspace->getName() !== 'live') {
            // only re-index on live changes
            return;
        }
        // TODO maybe this logic can be more sophisticated
        //    - only changes to node-structure is relevant here
        //      - add/remove/move documents
        //      - moved content nodes, etc
        $this->requiresReindexOfNodesToTheirDocuments = true;
    }

    /**
     * Called via AOP Signal Slot. See Package class.
     *
     * @param Site $site
     * @return void
     */
    public function onSiteImported(Site $site): void
    {
        $this->requiresReindexOfNodesToTheirDocuments = true;
    }

    public function shutdownObject(): void
    {
        if ($this->requiresReindexOfNodesToTheirDocuments === false) {
            // nothing to do if no node was changed during the request
            return;
        }
        $this->reindexNodesToTheirDocuments();
    }

    public function reindexNodesToTheirDocuments(): void
    {
        $databaseType = DatabaseType::detectDatabase($this->configurationManager);
        $sqlQuery = match ($databaseType) {
            DatabaseType::MYSQL, DatabaseType::MARIADB => <<<SQL
                call sandstorm_kissearch_populate_nodes_and_their_documents();
            SQL,
            DatabaseType::POSTGRES => <<<SQL
                refresh materialized view sandstorm_kissearch_nodes_and_their_documents;
            SQL,
            default => throw new UnsupportedDatabaseException(
                "Neos Content search does not support database of type '$databaseType->name'",
                1690389124
            )
        };
        $query = $this->entityManager->createNativeQuery($sqlQuery, new ResultSetMapping());
        $query->execute();
    }

}
