<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\AdditionalQueryParameterDefinition;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\AdditionalQueryParameterDefinitions;

#[Proxy(false)]
class NeosContentAdditionalParameters
{

    public const ADDITIONAL_QUERY_PARAM_NAME_SITE_NODE_NAME = 'neosContentSiteNodeName';
    public const ADDITIONAL_QUERY_PARAM_NAME_EXCLUDED_SITE_NODE_NAME = 'neosContentExcludedSiteNodeName';
    public const ADDITIONAL_QUERY_PARAM_NAME_DIMENSION_VALUES = 'neosContentDimensionValues';

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
