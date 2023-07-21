<?php

namespace Sandstorm\KISSearch\FusionObjects;

use Neos\Flow\Annotations\Inject;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultFrontend;
use Sandstorm\KISSearch\Service\SearchQuery;
use Sandstorm\KISSearch\Service\SearchService;

#[Scope('singleton')]
class SearchFrontendImplementation extends AbstractSearchImplementation
{

    #[Inject]
    protected SearchService $searchService;

    /**
     * @param SearchQuery $searchQuery
     * @return SearchResultFrontend[]
     */
    protected function doSearchQuery(SearchQuery $searchQuery): array
    {
        return $this->searchService->searchFrontend($searchQuery);
    }

}
