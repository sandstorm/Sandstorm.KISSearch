<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

use Neos\Flow\Annotations\Proxy;

/**
 * @Proxy(false)
 */
class SearchResultIdentifier
{

    private readonly string $identifier;

    /**
     * @param string $identifier
     */
    private function __construct(string $identifier)
    {
        $this->identifier = $identifier;
    }

    public static function create(string $identifier): SearchResultIdentifier
    {
        return new SearchResultIdentifier($identifier);
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function __toString(): string
    {
        return $this->identifier;
    }

}
