<?php

namespace Sandstorm\KISSearch\Eel;


use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Service\SearchQuery;
use Sandstorm\KISSearch\Service\SearchService;

#[Scope('singleton')]
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

    public function search(string $query, int $limit = 50, ?array $additionalParameters = null): array
    {
        return $this->searchService->search(new SearchQuery($query, $limit, $additionalParameters));
    }

    public function searchFrontend(string $query, int $limit = 50, ?array $additionalParameters = null): array
    {
        return $this->searchService->searchFrontend(new SearchQuery($query, $limit, $additionalParameters));
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
