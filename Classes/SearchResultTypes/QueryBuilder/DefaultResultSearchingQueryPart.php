<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Neos\Flow\Annotations\Proxy;

/**
 * @Proxy(false)
 */
class DefaultResultSearchingQueryPart implements ResultSearchingQueryPartInterface
{

    private readonly string $cteAlias;
    private readonly string $query;

    /**
     * @param string $cteAlias
     * @param string $query
     */
    public function __construct(string $cteAlias, string $query)
    {
        $this->cteAlias = $cteAlias;
        $this->query = $query;
    }

    function getSearchingQueryPart(): string
    {
        return <<<SQL
            $this->cteAlias as (
                $this->query
            )
        SQL;
    }
}
