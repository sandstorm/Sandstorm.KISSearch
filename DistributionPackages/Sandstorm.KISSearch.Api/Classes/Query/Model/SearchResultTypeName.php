<?php

namespace Sandstorm\KISSearch\Api\Query\Model;

readonly class SearchResultTypeName
{
    /**
     * @param string $name
     */
    private function __construct(private string $name)
    {
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

    public function __toString(): string
    {
        return $this->name;
    }

}
