<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Controller\Backend;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Annotations\Scope;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypesRegistry;
use Sandstorm\KISSearch\Service\SearchQueryInput;
use Sandstorm\KISSearch\Service\SearchService;

#[Scope('singleton')]
class BackendSearchController extends AbstractModuleController
{
    protected $defaultViewObjectName = FusionView::class;

    private readonly SearchService $searchService;

    private readonly SearchResultTypesRegistry $searchResultTypesRegistry;

    /**
     * @param SearchService $searchService
     * @param SearchResultTypesRegistry $searchResultTypesRegistry
     */
    public function __construct(SearchService $searchService, SearchResultTypesRegistry $searchResultTypesRegistry)
    {
        $this->searchService = $searchService;
        $this->searchResultTypesRegistry = $searchResultTypesRegistry;
    }

    /**
     * List known baskets
     *
     * @return void
     */
    public function indexAction(): void
    {
        $searchResultTypes = array_keys($this->searchResultTypesRegistry->getConfiguredSearchResultTypes());
        $fusionVars = [
            'searchResultTypes' => $searchResultTypes,
        ];

        $isSearchRequest = $this->request->getMainRequest()->hasArgument('search');

        if ($isSearchRequest) {
            $searchMode = $this->request->getMainRequest()->getArgument('search');
            $searchTerm = $this->request->getMainRequest()->getArgument('searchTerm');
            $globalLimit = intval($this->request->getMainRequest()->getArgument('globalLimit'));
            $limitPerResultType = [];
            foreach ($searchResultTypes as $type) {
                $limitPerResultType[$type] = intval($this->request->getMainRequest()->getArgument('limit_' . $type));
            }

            $input = new SearchQueryInput($searchTerm, []);
            if ($searchMode === 'Global Limit') {
                $searchResults = $this->searchService->search($input, $globalLimit);
            } else {
                $searchResults = $this->searchService->searchLimitPerResultType($input, $limitPerResultType);
            }

            $fusionVars['globalLimit'] = $globalLimit;
            $fusionVars['limit'] = $limitPerResultType;
            $fusionVars['searchTerm'] = $searchTerm;
            $fusionVars['searchResults'] = $searchResults;
        }

        $this->view->assignMultiple($fusionVars);
    }

}
