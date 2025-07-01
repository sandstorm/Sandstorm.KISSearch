<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\FrameworkAbstraction;

use Sandstorm\KISSearch\Api\Schema\SearchDependencyRefresherInterface;
use Sandstorm\KISSearch\Api\Schema\SearchSchemaInterface;

interface SchemaObjectInstanceProvider
{

    function getSearchSchemaInstance(string $className): SearchSchemaInterface;
    function getDependencyRefresherInstance(string $className): SearchDependencyRefresherInterface;

}