<?php

namespace Sandstorm\KISSearch\Neos\Schema\Model;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
readonly class NodeTypesSearchConfiguration
{
    public function __construct(
        private array $documentNodeTypeNames,
        private array $contentNodeTypeNames,
        private array $nodeTypeInheritance,
        // key: node type name, value: array of NodePropertyFulltextExtraction
        private array $extractorsForCritical,
        private array $extractorsForMajor,
        private array $extractorsForNormal,
        private array $extractorsForMinor,
    ) {
    }

    /**
     * @return array
     */
    public function getDocumentNodeTypeNames(): array
    {
        return $this->documentNodeTypeNames;
    }

    /**
     * @return array
     */
    public function getContentNodeTypeNames(): array
    {
        return $this->contentNodeTypeNames;
    }

    /**
     * @return array
     */
    public function getNodeTypeInheritance(): array
    {
        return $this->nodeTypeInheritance;
    }

    /**
     * @return array
     */
    public function getExtractorsForCritical(): array
    {
        return $this->extractorsForCritical;
    }

    /**
     * @return array
     */
    public function getExtractorsForMajor(): array
    {
        return $this->extractorsForMajor;
    }

    /**
     * @return array
     */
    public function getExtractorsForNormal(): array
    {
        return $this->extractorsForNormal;
    }

    /**
     * @return array
     */
    public function getExtractorsForMinor(): array
    {
        return $this->extractorsForMinor;
    }

    private static function extractorsArrayToString(string $identifier, array $allExtractors): string
    {
        // sort for deterministic output
        ksort($allExtractors);
        $arrayStringRepresentation = '_____';
        $arrayStringRepresentation .= $identifier;
        foreach ($allExtractors as $nodeTypeName => $extractors) {
            // sort for deterministic output
            ksort($extractors);
            $arrayStringRepresentation .= '_____';
            $arrayStringRepresentation .= $nodeTypeName;
            /** @var NodePropertyFulltextExtraction $extractor */
            foreach ($extractors as $propertyName => $extractor) {
                $arrayStringRepresentation .= '_____';
                $arrayStringRepresentation .= $propertyName;
                $arrayStringRepresentation .= '--=--';
                $arrayStringRepresentation .= $extractor->getMode()->name;
            }
        }
        return $arrayStringRepresentation;
    }

}
