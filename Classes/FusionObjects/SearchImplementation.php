<?php

namespace Sandstorm\KISSearch\FusionObjects;

use Neos\Flow\Annotations\Inject;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;
use Sandstorm\KISSearch\Service\SearchQueryInput;
use Sandstorm\KISSearch\Service\SearchService;

#[Scope('singleton')]
class SearchImplementation extends AbstractSearchImplementation
{

    #[Inject]
    protected SearchService $searchService;

    /**
     * @param SearchQueryInput $searchQuery
     * @return SearchResult[]
     */
    protected function doSearchQuery(SearchQueryInput $searchQuery): array
    {
        return $this->searchService->search($searchQuery);
    }

}
