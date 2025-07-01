<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\BackendSearch;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations\Around;
use Neos\Flow\Annotations\Aspect;
use Neos\Flow\Annotations\InjectConfiguration;
use Neos\Flow\Annotations\Pointcut;
use Neos\Flow\AOP\JoinPointInterface;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Neos\Ui\Fusion\Helper\NodeInfoHelper;
use Neos\Utility\ObjectAccess;
use Sandstorm\KISSearch\Api\Query\Configuration\SearchEndpointConfiguration;
use Sandstorm\KISSearch\Api\Query\Model\SearchInput;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;
use Sandstorm\KISSearch\Api\Query\Model\SearchResult;
use Sandstorm\KISSearch\Api\Query\Model\SearchResults;
use Sandstorm\KISSearch\Api\QueryTool;
use Sandstorm\KISSearch\Flow\DatabaseAdapter\DoctrineDatabaseAdapterService;
use Sandstorm\KISSearch\Flow\DatabaseTypeDetector;
use Sandstorm\KISSearch\Flow\FlowCDIObjectInstanceProvider;
use Sandstorm\KISSearch\Flow\FlowSearchEndpoints;
use Sandstorm\KISSearch\Neos\NeosContentSearchResultType;
use Sandstorm\KISSearch\Neos\Query\NeosDocumentResult;
use Sandstorm\KISSearch\Neos\Query\NeosQueryParameters;

#[Aspect]
class NeosBackendSearchService
{
    // state
    private ?SearchEndpointConfiguration $backendSearchEndpoint = null;
    private array $queryByContentRepository = [];

    #[InjectConfiguration(path: 'backendSearch.endpoint', package: 'Sandstorm.KISSearch.Neos')]
    protected ?string $endpointConfig;

    #[InjectConfiguration(path: 'backendSearch.enabled', package: 'Sandstorm.KISSearch.Neos')]
    protected ?bool $enabled;

    // dependencies

    public function __construct(
        private FlowCDIObjectInstanceProvider $instanceProvider,
        private DatabaseTypeDetector $databaseTypeDetector,
        private DoctrineDatabaseAdapterService $databaseAdapter,
        private ContentRepositoryRegistry $contentRepositoryRegistry,
        private NodeInfoHelper $nodeInfoHelper,
        private FlowSearchEndpoints $searchEndpoints
    ) {
    }

    #[Pointcut("method(Neos\Neos\Ui\Controller\BackendServiceController->flowQueryAction())")]
    public function flowQueryActionPointcut(): void {}

    #[Around("Sandstorm\KISSearch\Neos\BackendSearch\NeosBackendSearchService->flowQueryActionPointcut")]
    public function wrapAroundOriginalBackendService(JoinPointInterface $joinPoint): string
    {
        if (!$this->enabled) {
            // call original method, if feature is deactivated
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        }
        $chainArg = $joinPoint->getMethodArgument('chain');
        if (!is_array($chainArg)) {
            throw new \RuntimeException('Invalid AOP API access to Neos.Neos.UI BackendServiceController');
        }
        $chainElemCreateContext = $chainArg[0] ?? null;
        $chainElemSearch = $chainArg[1] ?? null;
        if ($chainElemCreateContext === null || $chainElemCreateContext['type'] !== 'createContext' ||
            $chainElemSearch === null || $chainElemSearch['type'] !== 'search') {
            // call original method, if no search was requested
            return $joinPoint->getAdviceChain()->proceed($joinPoint);
        } else {
            // ### use KISSearch

            if ($this->backendSearchEndpoint === null) {
                $this->backendSearchEndpoint = $this->searchEndpoints->getEndpointConfiguration($this->endpointConfig);
            }

            $request = ObjectAccess::getProperty($joinPoint->getProxy(), 'request', true);;
            if (!($request instanceof ActionRequest)) {
                throw new \RuntimeException(
                    'Invalid AOP API usage of Neos.Neos.UI BackendServiceController; cannot access action request'
                );
            }

            // 1. get arguments from request payload
            $query = $chainElemSearch['payload'][0];
            if (!is_string($query)) {
                throw new \RuntimeException(
                    'Invalid AOP API usage of Neos.Neos.UI BackendServiceController; cannot extract query from search payload'
                );
            }
            if (trim($query) === '') {
                return "[]";
            }
            $nodeContext = $chainElemCreateContext['payload'][0]['$node'] ?? null;
            if (!is_string($nodeContext)) {
                throw new \RuntimeException(
                    'Invalid AOP API usage of Neos.Neos.UI BackendServiceController; cannot extract $node context from search payload'
                );
            }
            $finisherFilter = $chainArg[2]['payload']['nodeTypeFilter'] ?? null;
            if (!is_string($finisherFilter)) {
                throw new \RuntimeException(
                    'Invalid AOP API usage of Neos.Neos.UI BackendServiceController; cannot extract nodeTypeFilter context from search payload'
                );
            }
            $nodeContextArray = json_decode($nodeContext, true);
            $rootNode = NodeAggregateId::fromString($nodeContextArray['aggregateId']);
            $contentRepositoryId = ContentRepositoryId::fromString($nodeContextArray['contentRepositoryId']);
            $workspace = WorkspaceName::fromString($nodeContextArray['workspaceName']);
            $nodeType = NodeTypeName::fromString($chainElemSearch['payload'][1]);
            $dimensionSpacePoint = DimensionSpacePoint::fromArray($nodeContextArray['dimensionSpacePoint']);
            $searchResults = $this->callKISSearchQuery(
                $query,
                $contentRepositoryId,
                $rootNode,
                $nodeType,
                $workspace,
                $dimensionSpacePoint
            );
            return self::returnAsOriginalExpectedFormat(
                $contentRepositoryId,
                $workspace,
                $dimensionSpacePoint,
                $finisherFilter,
                $request,
                $searchResults->getResults()
            );
        }
    }

    private function callKISSearchQuery(
        string $query,
        ContentRepositoryId $contentRepositoryId,
        NodeAggregateId $rootNode,
        NodeTypeName $nodeType,
        WorkspaceName $workspaceName,
        DimensionSpacePoint $dimensionSpacePoint
    ): SearchResults {
        if (!array_key_exists($contentRepositoryId->value, $this->queryByContentRepository)) {
            $this->queryByContentRepository[$contentRepositoryId->value] = SearchQuery::create(
                $this->databaseTypeDetector->detectDatabase(),
                $this->instanceProvider,
                $this->backendSearchEndpoint,
                [
                    NeosContentSearchResultType::OPTION_CONTENT_REPOSITORY => $contentRepositoryId->value
                ]
            );
        }
        $kissearchQuery = $this->queryByContentRepository[$contentRepositoryId->value];
        if (!$kissearchQuery instanceof SearchQuery) {
            throw new \RuntimeException(
                'no KISSearch query initialized for content repository ' . $contentRepositoryId->value
            );
        }

        $input = new SearchInput(
            $query,
            SearchInput::filterSpecificParameters('neos', [
                NeosQueryParameters::PARAM_NAME_ROOT_NODE => $rootNode,
                NeosQueryParameters::PARAM_NAME_INHERITED_DOCUMENT_NODE_TYPE => $nodeType,
                NeosQueryParameters::PARAM_NAME_WORKSPACE => $workspaceName,
                NeosQueryParameters::PARAM_NAME_DIMENSION_VALUES => $dimensionSpacePoint
            ]),
            [
                'neos-document' => 20
            ]
        );
        return QueryTool::executeSearchQuery(
            $this->databaseTypeDetector->detectDatabase(),
            $kissearchQuery,
            $input,
            $this->databaseAdapter
        );
    }

     /* Original API:
     {
        "contextPath": "{\"contentRepositoryId\":\"default\",\"workspaceName\":\"admin-a\",\"dimensionSpacePoint\":{\"language\":\"en_US\"},\"aggregateId\":\"0566668b-3b13-4172-a0e3-ccfb50c5c3a0\"}",
        "name": "node-ilusvipb5fe9x",
        "identifier": "0566668b-3b13-4172-a0e3-ccfb50c5c3a0",
        "nodeType": "Neos.Demo:Document.BlogPosting",
        "label": "Neos 8.0 \"Mad Hatter\" released",
        "isAutoCreated": false,
        "depth": 3,
        "children": [
            {
                "contextPath": "{\"contentRepositoryId\":\"default\",\"workspaceName\":\"admin-a\",\"dimensionSpacePoint\":{\"language\":\"en_US\"},\"aggregateId\":\"64b4ef8e-620c-7498-d3d3-f4e3e0d3c402\"}",
                "nodeType": "Neos.Demo:Collection.Content.Main"
            }
        ],
        "parent": "{\"contentRepositoryId\":\"default\",\"workspaceName\":\"admin-a\",\"dimensionSpacePoint\":{\"language\":\"en_US\"},\"aggregateId\":\"d87adae6-9e61-4dbc-b596-219abe1e45a2\"}",
        "matchesCurrentDimensions": true,
        "lastModificationDateTime": null,
        "creationDateTime": "2025-05-11T00:54:40+00:00",
        "lastPublicationDateTime": null,
        "properties": {
            "_hidden": false,
            "_hiddenInIndex": null,
            "_hasTimeableNodeVisibility": false
        },
        "uri": "http:\/\/localhost:8081\/neos\/preview?node=%7B%22contentRepositoryId%22%3A%22default%22%2C%22workspaceName%22%3A%22admin-a%22%2C%22dimensionSpacePoint%22%3A%7B%22language%22%3A%22en_US%22%7D%2C%22aggregateId%22%3A%220566668b-3b13-4172-a0e3-ccfb50c5c3a0%22%7D",
        "matched": true
    }
     */
    /**
     * @param array<SearchResult> $results
     * @return string
     * @throws \JsonException
     */
    private function returnAsOriginalExpectedFormat(
        ContentRepositoryId $contentRepositoryId,
        WorkspaceName $workspaceName,
        DimensionSpacePoint $contentDimensions,
        string $nodeTypeFilter,
        ActionRequest $request,
        array $results
    ): string
    {
        $cr = $this->contentRepositoryRegistry->get($contentRepositoryId);
        $subgraph = $cr->getContentSubgraph($workspaceName, $contentDimensions);

        $nodes = [];
        foreach ($results as $result) {
            $neosDocumentResult = NeosDocumentResult::fromSearchResult($result);
            $nodes[] = $subgraph->findNodeById($neosDocumentResult->getAggregateId());
        }

        $result = $this->nodeInfoHelper->renderNodesWithParents($nodes, $request, $nodeTypeFilter);
        return json_encode($result, JSON_THROW_ON_ERROR);
    }
}