<?php

namespace Sandstorm\KISSearch\Service;

use Neos\Flow\Annotations\Proxy;

/**
 * @Proxy(false)
 */
class SearchQueryInput
{
    private readonly string $query;

    private readonly ?array $searchTypeSpecificAdditionalParameters;

    private readonly ?string $language;

    /**
     * @param string $query the search terms
     * @param array|null $searchTypeSpecificAdditionalParameters
     * @param string|null $language
     */
    public function __construct(string $query, ?array $searchTypeSpecificAdditionalParameters = null, ?string $language = null)
    {
        $this->query = $query;
        $this->searchTypeSpecificAdditionalParameters = $searchTypeSpecificAdditionalParameters;
        $this->language = $language;
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

    /**
     * @return string|null
     */
    public function getLanguage(): ?string
    {
        return $this->language;
    }

}
