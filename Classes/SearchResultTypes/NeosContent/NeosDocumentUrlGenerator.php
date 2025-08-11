<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use GuzzleHttp\Psr7\ServerRequest;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\ContentRepository\Domain\Service\ContextFactoryInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Exception;
use Neos\Flow\Http\ServerRequestAttributes;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\Routing\Dto\RouteParameters;
use Neos\Flow\Mvc\Routing\Exception\MissingActionNameException;
use Neos\Flow\Mvc\Routing\UriBuilder;
use Sandstorm\KISSearch\SearchResultTypes\SearchResult;

/**
 * @Flow\Scope("singleton")
 */
class NeosDocumentUrlGenerator
{

    private readonly UriBuilder $uriBuilder;

    private readonly ContextFactoryInterface $contextFactory;

    private ?Context $contentContext = null;

    /**
     * @param UriBuilder $uriBuilder
     * @param ContextFactoryInterface $contextFactory
     */
    public function __construct(UriBuilder $uriBuilder, ContextFactoryInterface $contextFactory)
    {
        $this->uriBuilder = $uriBuilder;
        $this->contextFactory = $contextFactory;
    }

    /**
     * @param SearchResult $searchResult
     * @return string|null
     * @throws Exception
     * @throws MissingActionNameException
     */
    public function forSearchResult(SearchResult $searchResult): string|null
    {
        if ($this->contentContext === null) {
            // TODO add content dimensions
            $this->contentContext = $this->contextFactory->create([
                'workspaceName' => 'live',
                'invisibleContentShown' => false,
                'dimensions' => [],
                'targetDimensions' => []
            ]);
        }

        $node = $this->contentContext->getNodeByIdentifier($searchResult->getIdentifier()->getIdentifier());
        if ($node == null) {
            return null;
        }

        $nodeUri = $this->forNode($node);
        $metaData = $searchResult->getGroupMetaData();
        $primaryDomain = array_key_exists('primaryDomain', $metaData) ? $metaData['primaryDomain'] : null;
        if ($primaryDomain !== null) {
            return $primaryDomain . $nodeUri;
        }
        return $nodeUri;
    }

    /**
     * @param NodeInterface $node
     * @return string|null
     * @throws Exception
     * @throws MissingActionNameException
     */
    private function forNode(NodeInterface $node): string|null
    {
        $this->prepareUriBuilderForNeosLinks();
        return $this->uriBuilder->uriFor(
            'show',
            ['node' => $node],
            'Frontend\Node',
            'Neos.Neos'
        );
    }

    private function prepareUriBuilderForNeosLinks(): void
    {
        $httpRequest = new ServerRequest('GET', 'localhost');

        /**
         * set requestType to "new" for @see PluginUriAspect::rewritePluginViewUris
         */
        $httpRequest = $httpRequest->withAttribute('requestType', 'new');

        $httpRequest = $httpRequest->withAttribute(
            ServerRequestAttributes::ROUTING_PARAMETERS,
            // the parameter requestUriHost is required for the uriBuilder to work
            // the value does not matter and is not used
            RouteParameters::createEmpty()->withParameter('requestUriHost', 'neos.test')
        );
        $request = ActionRequest::fromHttpRequest($httpRequest);

        $this->uriBuilder
            ->reset()
            ->setCreateAbsoluteUri(false)
            ->setRequest($request);
    }

}
