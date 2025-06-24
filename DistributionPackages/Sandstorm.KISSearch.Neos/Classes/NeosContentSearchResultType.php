<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos;

use Sandstorm\KISSearch\Flow\InvalidConfigurationException;

class NeosContentSearchResultType
{

    public const BUCKET_COLUMN_CRITICAL = 'search_bucket_critical';
    public const BUCKET_COLUMN_MAJOR = 'search_bucket_major';
    public const BUCKET_COLUMN_NORMAL = 'search_bucket_normal';
    public const BUCKET_COLUMN_MINOR = 'search_bucket_minor';

    public const OPTION_CONTENT_REPOSITORY = 'contentRepository';

    public static function buildCRTableName_nodes(string $contentRepositoryId): string
    {
        return sprintf('cr_%s_p_graph_node', $contentRepositoryId);
    }

    public static function buildCRTableName_graphHierarchy(string $contentRepositoryId): string
    {
        return sprintf('cr_%s_p_graph_hierarchyrelation', $contentRepositoryId);
    }

    public static function buildCRTableName_dimensionSpacePoints(string $contentRepositoryId): string
    {
        return sprintf('cr_%s_p_graph_dimensionspacepoints', $contentRepositoryId);
    }

    public static function buildCRTableName_documentUriPath(string $contentRepositoryId): string
    {
        return sprintf('cr_%s_p_neos_documenturipath_uri', $contentRepositoryId);
    }

    public static function buildCRTableName_workspace(string $contentRepositoryId): string
    {
        return sprintf('cr_%s_p_graph_workspace', $contentRepositoryId);
    }

    public static function getContentRepositoryIdFromQueryOptions(string $identifier, array $queryOptions): string
    {
        $contentRepositoryId = $queryOptions[self::OPTION_CONTENT_REPOSITORY] ??
            throw new InvalidConfigurationException(sprintf("No '%s' option set in query options.", self::OPTION_CONTENT_REPOSITORY));
        if (!is_string($contentRepositoryId) || strlen(trim($contentRepositoryId)) === 0) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid query options '%s' found in %s; value must be a non-empty string but was: %s",
                    self::OPTION_CONTENT_REPOSITORY,
                    $identifier,
                    gettype($contentRepositoryId)
                )
            );
        }
        return $contentRepositoryId;
    }


}