<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class NodePropertyFulltextExtraction
{

    private readonly string $propertyName;

    private readonly FulltextExtractionMode $mode;

    /**
     * @param string $propertyName
     * @param FulltextExtractionMode $mode
     */
    public function __construct(string $propertyName, FulltextExtractionMode $mode)
    {
        $this->propertyName = $propertyName;
        $this->mode = $mode;
    }

    /**
     * @return string
     */
    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    /**
     * @return FulltextExtractionMode
     */
    public function getMode(): FulltextExtractionMode
    {
        return $this->mode;
    }

}
