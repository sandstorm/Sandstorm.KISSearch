<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class NodeTypesSearchConfiguration
{

    private readonly array $documentNodeTypeNames;

    private readonly array $contentNodeTypeNames;

    // key: node type name, value: array of NodePropertyFulltextExtraction

    private readonly array $extractorsForCritical;
    private readonly array $extractorsForMajor;
    private readonly array $extractorsForNormal;
    private readonly array $extractorsForMinor;

    /**
     * @param array $documentNodeTypeNames
     * @param array $contentNodeTypeNames
     * @param array $extractorsForCritical
     * @param array $extractorsForMajor
     * @param array $extractorsForNormal
     * @param array $extractorsForMinor
     */
    public function __construct(array $documentNodeTypeNames, array $contentNodeTypeNames, array $extractorsForCritical, array $extractorsForMajor, array $extractorsForNormal, array $extractorsForMinor)
    {
        $this->documentNodeTypeNames = $documentNodeTypeNames;
        $this->contentNodeTypeNames = $contentNodeTypeNames;
        $this->extractorsForCritical = $extractorsForCritical;
        $this->extractorsForMajor = $extractorsForMajor;
        $this->extractorsForNormal = $extractorsForNormal;
        $this->extractorsForMinor = $extractorsForMinor;
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

}
