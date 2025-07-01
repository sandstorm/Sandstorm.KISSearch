<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Eel;

use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\Query\Model\SearchInput;
use Sandstorm\KISSearch\Api\Query\Model\SearchQuery;
use Sandstorm\KISSearch\Api\Query\Model\SearchResults;
use Sandstorm\KISSearch\Api\QueryTool;
use Sandstorm\KISSearch\Flow\DatabaseAdapter\DoctrineDatabaseAdapterService;
use Sandstorm\KISSearch\Flow\DatabaseTypeDetector;
use Sandstorm\KISSearch\Flow\FlowCDIObjectInstanceProvider;
use Sandstorm\KISSearch\Flow\FlowSearchEndpoints;

#[Scope('singleton')]
class KISSearchHelper implements ProtectedContextAwareInterface
{

    public function __construct(
        protected readonly FlowSearchEndpoints $searchEndpoints,
        protected readonly DatabaseTypeDetector $databaseTypeDetector,
        protected readonly FlowCDIObjectInstanceProvider $instanceProvider,
        protected readonly DoctrineDatabaseAdapterService $databaseAdapter
    ) {
    }

    public static function input(string $searchQuery, array $parameters, array $resultTypeLimits, ?int $limit = null): SearchInput
    {
        return new SearchInput(
            $searchQuery,
            $parameters,
            $resultTypeLimits,
            $limit
        );
    }

    public function search(string $endpointId, SearchInput $input, array $queryOptions = [], ?string $databaseType = null): SearchResults
    {
        // ### 0. detect database type if not given explicitly
        if ($databaseType === null) {
            $databaseType = $this->databaseTypeDetector->detectDatabase();
        } else {
            $databaseType = DatabaseType::from($databaseType);
        }

        // ### 1. load the given endpoint configuration
        // In this case, we use the shipped Flow service.
        $searchEndpointConfiguration = $this->searchEndpoints->getEndpointConfiguration($endpointId);

        // ### 2. create the search query
        $searchQuery = SearchQuery::create(
            $databaseType,
            $this->instanceProvider,
            $searchEndpointConfiguration,
            // override default query options configured in the endpoint
            $queryOptions
        );

        // ### 3. execute the search query
        return QueryTool::executeSearchQuery(
            $databaseType,
            $searchQuery,
            $input,
            $this->databaseAdapter
        );
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}