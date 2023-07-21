<?php

namespace Sandstorm\KISSearch\Service;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class SearchQuery
{
    private readonly string $query;

    private readonly int $limit;

    /**
     * @param string $query the search terms
     * @param int $limit the result limit
     */
    public function __construct(string $query, int $limit)
    {
        $this->query = $query;
        $this->limit = $limit;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

}
