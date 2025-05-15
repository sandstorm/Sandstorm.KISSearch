<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Query;

use Sandstorm\KISSearch\Api\FrameworkAbstraction\QueryObjectInstanceProvider;
use Sandstorm\KISSearch\Api\Query\ResultFilterInterface;
use Sandstorm\KISSearch\Api\Query\SearchSourceInterface;
use Sandstorm\KISSearch\Api\Query\TypeAggregatorInterface;

readonly class NeosContentStandaloneInstanceQuery implements QueryObjectInstanceProvider
{

    private NeosContentQuery $standaloneInstance;

    /**
     * @param NeosContentQuery $testQuery
     */
    public function __construct(NeosContentQuery $testQuery = new NeosContentQuery())
    {
        $this->standaloneInstance = $testQuery;
    }


    function getSearchSourceInstance(string $identifier): SearchSourceInterface
    {
        return $this->standaloneInstance;
    }

    function getResultFilterInstance(string $identifier): ResultFilterInterface
    {
        return $this->standaloneInstance;
    }

    function getTypeAggregatorInstance(string $identifier): TypeAggregatorInterface
    {
        return $this->standaloneInstance;
    }

}