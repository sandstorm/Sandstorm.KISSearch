<?php

namespace Sandstorm\KISSearch\Service;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class SearchQueryInput
{
    private readonly string $query;

    private readonly ?int $limit;

    private readonly array $limitPerResultType;

    private readonly ?array $searchTypeSpecificAdditionalParameters;

    /**
     * @param string $query the search terms
     * @param int|null $limit the result limit. if not set, the sum of all search result type specific limits is used
     * @param array $limitPerResultType
     * @param array|null $searchTypeSpecificAdditionalParameters
     */
    public function __construct(string $query, ?int $limit, array $limitPerResultType, ?array $searchTypeSpecificAdditionalParameters = null)
    {
        $this->query = $query;
        $this->limit = $limit;
        $this->limitPerResultType = $limitPerResultType;
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
        if ($this->limit === null) {
            return array_sum($this->limitPerResultType);
        }
        return $this->limit;
    }

    /**
     * @return array
     */
    public function getLimitPerResultType(): array
    {
        return $this->limitPerResultType;
    }

    /**
     * @return array|null
     */
    public function getSearchTypeSpecificAdditionalParameters(): ?array
    {
        return $this->searchTypeSpecificAdditionalParameters;
    }

}
