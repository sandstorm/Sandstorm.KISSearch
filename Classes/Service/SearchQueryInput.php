<?php

namespace Sandstorm\KISSearch\Service;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class SearchQueryInput
{
    private readonly string $query;

    private readonly ?array $searchTypeSpecificAdditionalParameters;

    /**
     * @param string $query the search terms
     * @param array|null $searchTypeSpecificAdditionalParameters
     */
    public function __construct(string $query, ?array $searchTypeSpecificAdditionalParameters = null)
    {
        $this->query = $query;
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
     * @return array|null
     */
    public function getSearchTypeSpecificAdditionalParameters(): ?array
    {
        return $this->searchTypeSpecificAdditionalParameters;
    }

}
