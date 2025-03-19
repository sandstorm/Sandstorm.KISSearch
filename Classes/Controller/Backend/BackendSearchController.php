<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Controller\Backend;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Flow\Mvc\Exception\NoSuchArgumentException;
use Neos\Flow\Mvc\View\ViewInterface;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Sandstorm\KISSearch\InvalidConfigurationException;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypesRegistry;
use Sandstorm\KISSearch\Service\SearchQueryInput;
use Sandstorm\KISSearch\Service\SearchService;

#[Scope('singleton')]
class BackendSearchController extends AbstractModuleController
{
    protected $defaultViewObjectName = FusionView::class;

    private readonly SearchService $searchService;

    private readonly SearchResultTypesRegistry $searchResultTypesRegistry;

    private readonly ConfigurationManager $configurationManager;

    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);
        /** @var FusionView $view */
        $pathPatternsConfiguration = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.KISSearch.backendModule.fusionPathPatterns'
        );
        if (!is_array($pathPatternsConfiguration)) {
            throw new InvalidConfigurationException(
                sprintf(
                    'Configuration path Sandstorm.KISSearch.backendModule.fusionPathPatterns must resolve to an array; but was: %s',
                    $pathPatternsConfiguration !== null ? gettype($pathPatternsConfiguration) : 'null'
                ),
                1742424093
            );
        }
        $view->setFusionPathPatterns(
            $pathPatternsConfiguration
        );
    }

    /**
     * @param SearchService $searchService
     * @param SearchResultTypesRegistry $searchResultTypesRegistry
     * @param ConfigurationManager $configurationManager
     */
    public function __construct(
        SearchService $searchService,
        SearchResultTypesRegistry $searchResultTypesRegistry,
        ConfigurationManager $configurationManager
    )
    {
        $this->searchService = $searchService;
        $this->searchResultTypesRegistry = $searchResultTypesRegistry;
        $this->configurationManager = $configurationManager;
    }

    /**
     * @return void
     * @throws InvalidConfigurationTypeException
     * @throws NoSuchArgumentException
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
            $locale = $this->request->getMainRequest()->getArgument('locale');
            $searchTerm = $this->request->getMainRequest()->getArgument('searchTerm');
            $globalLimit = intval($this->request->getMainRequest()->getArgument('globalLimit'));
            $limitPerResultType = [];
            foreach ($searchResultTypes as $type) {
                $limitPerResultType[$type] = intval($this->request->getMainRequest()->getArgument('limit_' . $type));
            }

            $additionalParameters = $this->getAdditionalParametersFromFormRequest();

            $input = new SearchQueryInput($searchTerm, $additionalParameters, $locale);
            if ($searchMode === 'Global Limit') {
                $searchResults = $this->searchService->search($input, $globalLimit);
            } else {
                $searchResults = $this->searchService->searchLimitPerResultType($input, $limitPerResultType);
            }

            $fusionVars['globalLimit'] = $globalLimit;
            $fusionVars['limit'] = $limitPerResultType;
            $fusionVars['locale'] = $locale;
            $fusionVars['searchTerm'] = $searchTerm;
            $fusionVars['searchResults'] = $searchResults;
            $fusionVars['additionalParameters'] = $additionalParameters;
        }

        $this->view->assignMultiple($fusionVars);
    }

    private function getAdditionalParametersFromFormRequest(): array
    {
        $result = [];
        foreach ($this->request->getMainRequest()->getArguments() as $key => $value) {
            if (!str_starts_with($key, 'additionalParameter__')) {
                continue;
            }
            $result[substr($key, 21)] = $value;
        }
        return $result;
    }

}
