<?php

namespace Sandstorm\KISSearch\Service;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class SearchQuery
{
    private readonly string $query;

    private readonly int $limit;

    private readonly ?array $searchTypeSpecificAdditionalParameters;

    /**
     * @param string $query the search terms
     * @param int $limit the result limit
     * @param array|null $searchTypeSpecificAdditionalParameters
     */
    public function __construct(string $query, int $limit, ?array $searchTypeSpecificAdditionalParameters = null)
    {
        $this->query = $query;
        $this->limit = $limit;
        $this->searchTypeSpecificAdditionalParameters = $searchTypeSpecificAdditionalParameters;
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

    /**
     * @return array|null
     */
    public function getSearchTypeSpecificAdditionalParameters(): ?array
    {
        return $this->searchTypeSpecificAdditionalParameters;
    }

}
