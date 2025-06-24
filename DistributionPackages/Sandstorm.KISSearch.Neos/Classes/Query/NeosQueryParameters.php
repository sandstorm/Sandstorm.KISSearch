<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Query;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Sandstorm\KISSearch\Api\Query\QueryParameters;

class NeosQueryParameters
{

    public const PARAM_NAME_WORKSPACE = 'workspace';
    public const PARAM_NAME_SITE_NODE_NAME = 'site_node';
    public const PARAM_NAME_EXCLUDE_SITE_NODE_NAME = 'exclude_site_node';
    public const PARAM_NAME_DIMENSION_VALUES = 'dimension_values';
    public const PARAM_NAME_ROOT_NODE = 'root_node';

    # params for document search
    public const PARAM_NAME_CONTENT_NODE_TYPES = 'content_node_types';
    public const PARAM_NAME_DOCUMENT_NODE_TYPES = 'document_node_types';
    public const PARAM_NAME_INHERITED_CONTENT_NODE_TYPE = 'inherited_content_node_type';
    public const PARAM_NAME_INHERITED_DOCUMENT_NODE_TYPE = 'inherited_document_node_type';

    public static function create(string $resultFilterIdentifier): QueryParameters
    {
        return (new QueryParameters())
            ->addFilterSpecificMapper($resultFilterIdentifier, self::PARAM_NAME_SITE_NODE_NAME, function ($rawValue) {
                return self::nodeNameMapper($rawValue);
            })
            ->addFilterSpecificMapper(
                $resultFilterIdentifier,
                self::PARAM_NAME_EXCLUDE_SITE_NODE_NAME,
                function ($rawValue) {
                    return self::nodeNameMapper($rawValue);
                }
            )
            ->addFilterSpecificMapper($resultFilterIdentifier, self::PARAM_NAME_DIMENSION_VALUES, function ($rawValue) {
                return self::dimensionValuesMapper($rawValue);
            })
            ->addFilterSpecificMapper($resultFilterIdentifier, self::PARAM_NAME_WORKSPACE, function ($rawValue) {
                if ($rawValue instanceof WorkspaceName) {
                    return $rawValue->value;
                }
                return $rawValue;
            })
            ->addFilterSpecificMapper($resultFilterIdentifier, self::PARAM_NAME_ROOT_NODE, function ($rawValue) {
                if ($rawValue instanceof NodeAggregateId) {
                    return $rawValue->value;
                }
                return $rawValue;
            })
            ->addFilterSpecificMapper(
                $resultFilterIdentifier,
                self::PARAM_NAME_CONTENT_NODE_TYPES,
                function ($rawValue) {
                    return self::nodeTypeNameMapper($rawValue);
                }
            )
            ->addFilterSpecificMapper(
                $resultFilterIdentifier,
                self::PARAM_NAME_DOCUMENT_NODE_TYPES,
                function ($rawValue) {
                    return self::nodeTypeNameMapper($rawValue);
                }
            )
            ->addFilterSpecificMapper(
                $resultFilterIdentifier,
                self::PARAM_NAME_INHERITED_CONTENT_NODE_TYPE,
                function ($rawValue) {
                    return self::singleNodeTypeNameMapper($rawValue);
                }
            )
            ->addFilterSpecificMapper(
                $resultFilterIdentifier,
                self::PARAM_NAME_INHERITED_DOCUMENT_NODE_TYPE,
                function ($rawValue) {
                    return self::singleNodeTypeNameMapper($rawValue);
                }
            );
    }

    /**
     * @param string[]|string|NodeName|NodeName[] $nodeNames
     * @return string[]
     */
    private static function nodeNameMapper(string|NodeName|array $nodeNames): array
    {
        if (is_array($nodeNames)) {
            return array_map(function (mixed $nodeName) {
                if ($nodeName instanceof NodeName) {
                    return $nodeName->__toString();
                }
                return $nodeName;
            }, $nodeNames);
        }
        if ($nodeNames instanceof NodeName) {
            return [$nodeNames->__toString()];
        }
        return [(string)$nodeNames];
    }

    /**
     * @param string[]|string|NodeTypeName|NodeTypeName[] $nodeTypeNames
     * @return string[]
     */
    private static function nodeTypeNameMapper(string|NodeTypeName|array $nodeTypeNames): array
    {
        if (is_array($nodeTypeNames)) {
            return array_map(function (mixed $nodeTypeName) {
                if ($nodeTypeName instanceof NodeTypeName) {
                    return $nodeTypeName->value;
                }
                return $nodeTypeName;
            }, $nodeTypeNames);
        }
        if ($nodeTypeNames instanceof NodeTypeName) {
            return [$nodeTypeNames->value];
        }
        return [(string)$nodeTypeNames];
    }

    /**
     * @param string|NodeTypeName $nodeTypeNames
     * @return string[]
     */
    private static function singleNodeTypeNameMapper(string|NodeTypeName $nodeTypeNames): array
    {
        if ($nodeTypeNames instanceof NodeTypeName) {
            return [$nodeTypeNames->value];
        }
        return [(string)$nodeTypeNames];
    }

    private static function dimensionValuesMapper(array|DimensionSpacePoint $input): string
    {
        if ($input instanceof DimensionSpacePoint) {
            $values = $input->coordinates;
        } else {
            $values = $input;
        }

        $result = [];
        foreach ($values as $dimensionName => $dimensionValue) {
            $result[] = [
                'dimension_name' => $dimensionName,
                'filter_value' => $dimensionValue
            ];
        }
        return json_encode($result);
    }

}