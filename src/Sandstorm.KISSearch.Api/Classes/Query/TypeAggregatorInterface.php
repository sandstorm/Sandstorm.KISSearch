<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;

/**
 * <b>Core extensibility API</b>
 * <br/>
 * Creates the SQL part, that aggregates results emitted by all filters for the same result type to search results.
 * F.e. it aggregates multiple matches of the same item from multiple sources to one single result item.
 * Another use-case is the Neos ContentRepository: the filter might emmit multiple Content Nodes that are aggregated
 * to their closest parent Document Node as the logical search result item.
 *
 * You can implement how you want the scores of multiple matches to be combined here. Most likely you want ether on of:
 *  - summing the score (more matches == more relevant)
 *  - averaging the score (matches even out)
 *  - take the max score (more matches do not matter, the most relevant match sets the score)
 */
interface TypeAggregatorInterface
{

    function getResultTypeAggregatorQueryPart(
        DatabaseType $databaseType,
        string $resultTypeName,
        array $mergingQueryParts,
        array $queryOptions,
        array $aggregatorOptions
    ): string;

}