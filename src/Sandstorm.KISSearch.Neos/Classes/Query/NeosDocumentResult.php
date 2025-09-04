<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Query;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\KISSearch\Api\Query\Model\SearchResult;

readonly class NeosDocumentResult
{
    private function __construct(
        private SearchResult $result,
        private NodeAddress $nodeAddress,
        private NodeName $nodeName,
        private NodeAggregateId $aggregateId,
        private NodeTypeName $nodeType
    )
    {
    }

    public static function fromSearchResult(SearchResult $result): self
    {
        $metaData = $result->getGroupMetaData();
        return new self(
            $result,
            NodeAddress::create(
                ContentRepositoryId::fromString($metaData['contentRepositoryId']),
                WorkspaceName::fromString($metaData['workspace']),
                DimensionSpacePoint::fromArray($metaData['dimensionValues']),
                NodeAggregateId::fromString($metaData['documentAggregateId'])
            ),
            self::optionalNodeName($metaData['documentNodeName']),
            NodeAggregateId::fromString($metaData['documentAggregateId']),
            NodeTypeName::fromString($metaData['documentNodeType'])
        );
    }

    private static function optionalNodeName(?string $rawValue): ?NodeName
    {
        if ($rawValue === null) {
            return null;
        }
        return NodeName::fromString($rawValue);
    }

    /**
     * @return SearchResult
     */
    public function getResult(): SearchResult
    {
        return $this->result;
    }

    /**
     * @return NodeAddress
     */
    public function getNodeAddress(): NodeAddress
    {
        return $this->nodeAddress;
    }

    /**
     * @return NodeName
     */
    public function getNodeName(): NodeName
    {
        return $this->nodeName;
    }

    /**
     * @return NodeAggregateId
     */
    public function getAggregateId(): NodeAggregateId
    {
        return $this->aggregateId;
    }

    public function getNodeType(): NodeTypeName
    {
        return $this->nodeType;
    }

}