<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Query;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\KISSearch\Api\SearchResult;

readonly class NeosDocumentResult
{
    public function __construct(
        private SearchResult $result
    )
    {
    }

    public function getNodeAddress(): NodeAddress
    {
        $metaData = $this->result->getMetaData();
        return NodeAddress::create(
            ContentRepositoryId::fromString($metaData['contentRepository']),
            WorkspaceName::fromString($metaData['workspace']),
            DimensionSpacePoint::fromArray($metaData['dimensionValues']),
            NodeAggregateId::fromString($metaData['documentNodeIdentifier'])
        );
    }
}