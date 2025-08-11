<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\Flow\Annotations\Proxy;

/**
 * @Proxy(false)
 */
class NeosContentAdditionalParameters
{

    public const SITE_NODE_NAME = 'neosContentSiteNodeName';
    public const EXCLUDED_SITE_NODE_NAME = 'neosContentExcludedSiteNodeName';
    public const DIMENSION_VALUES = 'neosContentDimensionValues';
    public const DOCUMENT_NODE_TYPES = 'neosContentDocumentNodeTypes';

    public static function nodeNameMapper(mixed $nodeNames): ?array {
        if ($nodeNames === null) {
            return null;
        }
        if (is_array($nodeNames)) {
            return array_map(function(mixed $nodeName) {
                if ($nodeName instanceof NodeName) {
                    return $nodeName->__toString();
                }
                return $nodeName;
            }, $nodeNames);
        }
        if ($nodeNames instanceof NodeName) {
            return [$nodeNames->__toString()];
        }
        return [(string) $nodeNames];
    }

}
