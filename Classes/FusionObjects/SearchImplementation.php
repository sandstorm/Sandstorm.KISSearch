<?php

namespace Sandstorm\KISSearch\FusionObjects;

use Sandstorm\KISSearch\Service\SearchQueryInput;
use Sandstorm\KISSearch\Service\SearchService;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope('singleton')
 */
class SearchImplementation extends AbstractSearchImplementation
{

    /**
     * @Flow\Inject
     *
     * @var SearchService
     */
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
