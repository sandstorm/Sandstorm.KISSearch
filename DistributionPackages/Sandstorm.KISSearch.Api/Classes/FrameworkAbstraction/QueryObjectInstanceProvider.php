<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\FrameworkAbstraction;

use Sandstorm\KISSearch\Api\Query\ResultFilterInterface;
use Sandstorm\KISSearch\Api\Query\SearchSourceInterface;
use Sandstorm\KISSearch\Api\Query\TypeAggregatorInterface;

interface QueryObjectInstanceProvider
{

    function getSearchSourceInstance(string $identifier): SearchSourceInterface;
    function getResultFilterInstance(string $identifier): ResultFilterInterface;
    function getTypeAggregatorInstance(string $identifier): TypeAggregatorInterface;

}