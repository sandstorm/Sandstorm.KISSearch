<?php

namespace Sandstorm\KISSearch\FusionObjects;

use Neos\Flow\Annotations\Inject;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Service\SearchQueryInput;
use Sandstorm\KISSearch\Service\SearchService;

#[Scope('singleton')]
class SearchImplementation extends AbstractSearchImplementation
{

    #[Inject]
    protected SearchService $searchService;

    public function evaluate(): array
    {
        $query = $this->getQuery();
        if ($query === null) {
            // no query, no results! it's that simple ;)
            return [];
        }
        $limit = $this->getLimit();
        $searchQuery = new SearchQueryInput(
            $query,
            $this->getAdditionalParameters(),
            $this->getLanguage()
        );
        return $this->searchService->search($searchQuery, $limit);
    }

}
