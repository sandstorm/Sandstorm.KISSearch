<?php

namespace Sandstorm\KISSearch\Eel;


use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Sandstorm\KISSearch\Service\SearchQueryInput;
use Sandstorm\KISSearch\Service\SearchService;

/**
 * @Flow\Scope("singleton")
 */
class SearchHelper implements ProtectedContextAwareInterface
{

    private readonly SearchService $searchService;

    /**
     * @param SearchService $searchService
     */
    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    public function search(string $query, int $limit = 50, ?array $additionalParameters = null, ?string $language = null): array
    {
        return $this->searchService->search(new SearchQueryInput($query, $additionalParameters, $language), $limit);
    }

    public function searchFrontend(string $query, int $limit = 50, ?array $additionalParameters = null, ?string $language = null): array
    {
        return $this->searchService->searchFrontend(new SearchQueryInput($query, $additionalParameters, $language), $limit);
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
