<?php

namespace Sandstorm\KISSearch\Service;

use Neos\ContentRepository\Domain\Service\Context;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Domain\Service\NodeSearchServiceInterface;
use Sandstorm\KISSearch\SearchResultTypes\NeosContent\NeosContentAdditionalParameters;
use Sandstorm\KISSearch\SearchResultTypes\NeosContent\NeosContentSearchResultType;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;

class KISSearchNodeSearchService implements NodeSearchServiceInterface
{

    private readonly SearchService $searchService;

    public function __construct(SearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * @throws InvalidConfigurationTypeException
     */
    public function findByProperties($term, array $searchNodeTypes, Context $context): array
    {
        /**
         * @var $contentContext ContentContext
         */
        $contentContext = $context;

        // TODO handle this better: new API in KISSearch that looks inside language content dimension of a node and returns the PG TS language
        try {
            $language = $this->searchService->getDefaultLanguage();
        } catch (InvalidConfigurationTypeException $e) {
            $language = 'simple';
        }
        $siteNodeName = $contentContext->getCurrentSite()->getNodeName();
        $query = new SearchQueryInput(
            $term,
            [
                NeosContentSearchResultType::TYPE_NAME => [
                    NeosContentAdditionalParameters::ADDITIONAL_QUERY_PARAM_NAME_SITE_NODE_NAME => $siteNodeName,
                    NeosContentAdditionalParameters::ADDITIONAL_QUERY_PARAM_NAME_DOCUMENT_NODE_TYPES => count($searchNodeTypes) > 0 ? $searchNodeTypes : null
                    //NeosContentAdditionalParameters::ADDITIONAL_QUERY_PARAM_NAME_DIMENSION_VALUES => TODO dimension values
                    // TODO new parameter: workspace
                ]
            ],
            $language
        );
        $searchResults = $this->searchService->search($query, 1000);
        return $this->searchResultsToNodes($contentContext, $searchResults);
    }

    /**
     * @param ContentContext $contentContext
     * @param SearchResult[] $searchResults
     * @return array
     */
    protected function searchResultsToNodes(ContentContext $contentContext, array $searchResults): array
    {
        $searchResultNodes = [];
        foreach ($searchResults as $searchResult) {
            if ($searchResult->getResultTypeName()->getName() !== NeosContentSearchResultType::TYPE_NAME) {
                continue;
            }
            $searchResultNodes[] = $contentContext->getNodeByIdentifier($searchResult->getIdentifier()->getIdentifier());
        }
        return $searchResultNodes;
    }
}
