<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class CustomResultMergingQueryPart implements ResultMergingQueryPartInterface
{

    private readonly string $customQuery;

    /**
     * @param string $customQuery
     */
    public function __construct(string $customQuery)
    {
        $this->customQuery = $customQuery;
    }

    function getMergingQueryPart(): string
    {
        return $this->customQuery;
    }
}
