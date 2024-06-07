<?php

namespace Sandstorm\KISSearch\FusionObjects;

use Neos\Flow\Annotations\Inject;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Sandstorm\KISSearch\Service\SearchQueryInput;
use Sandstorm\KISSearch\Service\SearchService;

#[Scope('singleton')]
class SearchLimitPerResultTypeImplementation extends AbstractSearchImplementation
{

    #[Inject]
    protected SearchService $searchService;

    /**
     * @throws InvalidConfigurationTypeException
     */
    public function evaluate()
    {
        $query = $this->getQuery();
        if ($query === null) {
            // no query, no results! it's that simple ;)
            return [];
        }
        $limit = $this->getLimitPerResultType();
        $searchQuery = new SearchQueryInput(
            $query,
            $this->getAdditionalParameters(),
            $this->getLanguage()
        );
        return $this->searchService->searchLimitPerResultType($searchQuery, $limit);
    }

}
