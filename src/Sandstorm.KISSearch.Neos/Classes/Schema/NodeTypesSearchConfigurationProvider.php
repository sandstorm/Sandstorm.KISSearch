<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Schema;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Eel\EelEvaluatorInterface;
use Neos\Eel\Utility as EelUtility;
use Neos\Flow\Annotations\Inject;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Flow\InvalidConfigurationException;
use Sandstorm\KISSearch\Flow\InvalidNodeTypeSearchConfigurationException;
use Sandstorm\KISSearch\Neos\Eel\IndexingHelper;
use Sandstorm\KISSearch\Neos\Schema\Model\FulltextExtractionInstruction;
use Sandstorm\KISSearch\Neos\Schema\Model\FulltextExtractionMode;
use Sandstorm\KISSearch\Neos\Schema\Model\NodePropertyFulltextExtraction;
use Sandstorm\KISSearch\Neos\Schema\Model\NodeTypesSearchConfiguration;
use Sandstorm\KISSearch\Neos\SearchBucket;

/**
 * Calculates the {@link NodeTypesSearchConfiguration} from the NodeTypes configuration for the given content repository.
 *
 * Implements downwards compatibility for the configuration format of `Neos.SearchPlugin`.
 */
#[Scope('singleton')]
class NodeTypesSearchConfigurationProvider
{

    #[Inject]
    protected EelEvaluatorInterface $eelEvaluator;
    #[Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    protected array $contentRepositoryCache = [];

    private function getContentRepository(string $contentRepositoryId): ContentRepository
    {
        if (!array_key_exists($contentRepositoryId, $this->contentRepositoryCache)) {
            $this->contentRepositoryCache[$contentRepositoryId] = $this->contentRepositoryRegistry->get(
                ContentRepositoryId::fromString($contentRepositoryId)
            );
        }
        return $this->contentRepositoryCache[$contentRepositoryId];
    }

    private function validateExcludedNodeTypesConfiguration(NodeTypeManager $nodeTypeManager, array $config): void
    {
        foreach ($config as $excludedNodeType) {
            // check if configured node type exists
            if (!$nodeTypeManager->hasNodeType($excludedNodeType)) {
                throw new InvalidConfigurationException(
                    sprintf(
                        "Invalid search source option; excluded node type '%s' does not exist",
                        $excludedNodeType
                    ),
                    1690071420
                );
            }
        }
    }

    private function validateNodeTypeConfiguration(NodeTypeManager $nodeTypeManager, string $nodeTypeName, string $expectedSuperType): void
    {
        // check if configured node type exists
        if (!$nodeTypeManager->hasNodeType($nodeTypeName)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid base node type configuration; node type '%s' does not exist",
                    $nodeTypeName
                ),
                1690071560
            );
        }
        // check if configured node type extends or is equal to the expected super type
        $nodeType = $nodeTypeManager->getNodeType($nodeTypeName);
        if (!$nodeType->isOfType($expectedSuperType)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid base node type configuration; configured node type '%s' must extend or be of type '%s'",
                    $nodeType->name->value,
                    $expectedSuperType
                ),
                1690071423
            );
        }
    }

    public function getNodeTypesSearchConfiguration(
        string $contentRepositoryId,
        array $excludedNodeTypes,
        string $baseDocumentNodeType,
        string $baseContentNodeType
    ): NodeTypesSearchConfiguration
    {
        $contentRepository = $this->getContentRepository($contentRepositoryId);
        $nodeTypeManager = $contentRepository->getNodeTypeManager();

        $this->validateExcludedNodeTypesConfiguration($nodeTypeManager, $excludedNodeTypes);

        $this->validateNodeTypeConfiguration($nodeTypeManager, $baseDocumentNodeType, 'Neos.Neos:Document');
        $this->validateNodeTypeConfiguration($nodeTypeManager, $baseContentNodeType, 'Neos.Neos:Content');

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