<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Schema;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Utility as EelUtility;
use Neos\Flow\Annotations\Inject;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\Configuration\ConfigurationManager;
use Sandstorm\KISSearch\Flow\InvalidConfigurationException;
use Sandstorm\KISSearch\Flow\InvalidNodeTypeSearchConfigurationException;
use Sandstorm\KISSearch\Neos\Eel\IndexingHelper;
use Sandstorm\KISSearch\Neos\Schema\Model\FulltextExtractionInstruction;
use Sandstorm\KISSearch\Neos\Schema\Model\FulltextExtractionMode;
use Sandstorm\KISSearch\Neos\Schema\Model\NodePropertyFulltextExtraction;
use Sandstorm\KISSearch\Neos\Schema\Model\NodeTypesSearchConfiguration;
use Sandstorm\KISSearch\Neos\SearchBucket;

#[Scope('singleton')]
class NeosContentSchemaConfiguration
{

    #[Inject]
    protected ConfigurationManager $configurationManager;
    #[Inject]
    protected EelEvaluatorInterface $eelEvaluator;
    #[Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    protected ?ContentRepository $contentRepository = null;

    private function getContentRepository(): ContentRepository
    {
        if ($this->contentRepository === null) {
            $contentRepositoryIdConfig = $this->configurationManager->getConfiguration(
                ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                'Sandstorm.KISSearch.contentRepositoryId'
            );
            if (!is_string($contentRepositoryIdConfig) || strlen(trim($contentRepositoryIdConfig)) === 0) {
                throw new InvalidConfigurationException(
                    "No content repository ID configured. Set the value via 'Sandstorm.KISSearch.contentRepositoryId'"
                );
            }
            $contentRepositoryId = ContentRepositoryId::fromString($contentRepositoryIdConfig);
            $this->contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);
        }
        return $this->contentRepository;
    }

    public function getNodeTypesSearchConfiguration(): NodeTypesSearchConfiguration
    {
        $excludedNodeTypes = $this->getExcludedNodeTypesConfiguration();
        $baseDocumentNodeType = $this->getBaseDocumentNodeTypeConfiguration();
        $baseContentNodeType = $this->getBaseContentNodeTypeConfiguration();

        $nodeTypeManager = $this->getContentRepository()->getNodeTypeManager();

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
            $nodeTypeManager->getSubNodeTypes($baseDocumentNodeType, false),
            $exclusionFilter
        );

        $contentNodeTypes = array_filter(
            $nodeTypeManager->getSubNodeTypes($baseContentNodeType, false),
            $exclusionFilter
        );

        $allSearchableNodeTypes = array_merge($documentNodeTypes, $contentNodeTypes);

        $extractorsForCritical = [];
        $extractorsForMajor = [];
        $extractorsForNormal = [];
        $extractorsForMinor = [];
        $nodeTypeInheritance = [];
        /** @var NodeType $searchableNodeType */
        foreach ($allSearchableNodeTypes as $searchableNodeType) {
            $nodeTypeName = $searchableNodeType->name->value;
            $addExtraction = function (NodePropertyFulltextExtraction $extraction, SearchBucket $targetBucket) use (
                $nodeTypeName,
                &$extractorsForCritical,
                &$extractorsForMajor,
                &$extractorsForNormal,
                &$extractorsForMinor
            ) {
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
                $searchConfiguration = array_key_exists(
                    'search',
                    $propertyConfiguration
                ) ? $propertyConfiguration['search'] : [];
                if (empty($searchConfiguration)) {
                    continue;
                }

                // new configuration format
                $bucketConfiguration = array_key_exists(
                    'bucket',
                    $searchConfiguration
                ) ? $searchConfiguration['bucket'] : null;
                $extractHtmlIntoConfiguration = array_key_exists(
                    'extractHtmlInto',
                    $searchConfiguration
                ) ? $searchConfiguration['extractHtmlInto'] : null;
                // backwards compatibility to SearchPlugin
                $fulltextExtractorConfiguration = array_key_exists(
                    'fulltextExtractor',
                    $searchConfiguration
                ) ? $searchConfiguration['fulltextExtractor'] : null;

                // validate configuration, only one key must be specified
                if ($bucketConfiguration !== null && $extractHtmlIntoConfiguration !== null) {
                    throw new InvalidNodeTypeSearchConfigurationException(
                        "Property '$propertyName' of node type '$nodeTypeName' has invalid search configuration; only one of 'bucket' or 'extractHtmlInto' must be set.",
                        1689765367
                    );
                }

                if ($bucketConfiguration !== null) {
                    $targetBucket = SearchBucket::from($bucketConfiguration);
                    $extraction = new NodePropertyFulltextExtraction(
                        $propertyName,
                        FulltextExtractionMode::EXTRACT_INTO_SINGLE_BUCKET
                    );
                    $addExtraction($extraction, $targetBucket);
                } else {
                    if ($extractHtmlIntoConfiguration !== null) {
                        $targetBuckets = null;
                        if ($extractHtmlIntoConfiguration === 'all') {
                            // all shortcut
                            $targetBuckets = SearchBucket::allBuckets();
                        } else {
                            if (is_array($extractHtmlIntoConfiguration)) {
                                // specific set of buckets
                                $targetBuckets = array_map(function (string $bucketConfiguration) {
                                    return SearchBucket::from($bucketConfiguration);
                                }, $extractHtmlIntoConfiguration);
                            } else {
                                // single target bucket
                                $targetBuckets = [SearchBucket::from($extractHtmlIntoConfiguration)];
                            }
                        }
                        $extraction = new NodePropertyFulltextExtraction(
                            $propertyName,
                            FulltextExtractionMode::EXTRACT_HTML_TAGS
                        );
                        foreach ($targetBuckets as $targetBucket) {
                            $addExtraction($extraction, $targetBucket);
                        }
                    } else {
                        if ($fulltextExtractorConfiguration !== null) {
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
            }

            // inheritance
            $nodeTypeInheritance[$searchableNodeType->name->value] = [$searchableNodeType->name->value];
            $nodeTypeInheritance[$searchableNodeType->name->value] = array_merge(
                $nodeTypeInheritance[$searchableNodeType->name->value],
                array_keys($searchableNodeType->getFullConfiguration()['superTypes'])
            );
        }

        return new NodeTypesSearchConfiguration(
            array_map(function (NodeType $documentNodeType) {
                return $documentNodeType->name->value;
            }, $documentNodeTypes),
            array_map(function (NodeType $contentNodeType) {
                return $contentNodeType->name->value;
            }, $contentNodeTypes),
            $nodeTypeInheritance,
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
                sprintf(
                    "Configuration 'Sandstorm.KISSearch.neosContent.excludedNodeTypes' must be an array; but was: %s",
                    gettype($excludedNodeTypes)
                ),
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
        $nodeTypeManager = $this->getContentRepository()->getNodeTypeManager();
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
        if (!$nodeTypeManager->hasNodeType($baseNodeType)) {
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
        $nodeType = $nodeTypeManager->getNodeType($baseNodeType);
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

    private function addExtractionForBucket(
        string $nodeTypeName,
        NodePropertyFulltextExtraction $extraction,
        &$bucketConfiguration
    ): void {
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

}