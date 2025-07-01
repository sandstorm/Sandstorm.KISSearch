<?php

namespace Sandstorm\KISSearch\Neos\Schema\Model;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
readonly class NodePropertyFulltextExtraction
{

    /**
     * @param string $propertyName
     * @param FulltextExtractionMode $mode
     */
    public function __construct(
        private string $propertyName,
        private FulltextExtractionMode $mode
    )
    {
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
