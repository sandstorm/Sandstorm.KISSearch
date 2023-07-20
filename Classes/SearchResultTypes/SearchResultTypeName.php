<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class SearchResultTypeName
{

    private readonly string $name;

    /**
     * @param string $name
     */
    private function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function create(string $name): SearchResultTypeName
    {
        return new SearchResultTypeName($name);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

}
