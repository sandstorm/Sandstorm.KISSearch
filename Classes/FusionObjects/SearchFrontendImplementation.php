<?php

namespace Sandstorm\KISSearch\FusionObjects;

use Neos\Flow\Annotations\Inject;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultFrontend;
use Sandstorm\KISSearch\Service\SearchQueryInput;
use Sandstorm\KISSearch\Service\SearchService;

#[Scope('singleton')]
class SearchFrontendImplementation extends AbstractSearchImplementation
{

    #[Inject]
    protected SearchService $searchService;

    /**
     * @param SearchQueryInput $searchQuery
     * @param int $limit
     * @return SearchResultFrontend[]
     */
    protected function doSearchQuery(SearchQueryInput $searchQuery, int $limit): array
    {
        return $this->searchService->searchFrontend($searchQuery, $limit);
    }

}
