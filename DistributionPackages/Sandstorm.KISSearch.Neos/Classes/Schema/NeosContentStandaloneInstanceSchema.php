<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Neos\Schema;

use Sandstorm\KISSearch\Api\FrameworkAbstraction\SchemaObjectInstanceProvider;
use Sandstorm\KISSearch\Api\Schema\SearchSchemaInterface;

readonly class NeosContentStandaloneInstanceSchema implements SchemaObjectInstanceProvider
{

    /**
     * @param NeosContentSearchSchema $standaloneInstance
     */
    public function __construct(private NeosContentSearchSchema $standaloneInstance = new NeosContentSearchSchema())
    {
    }

    function getSearchSchemaInstance(string $className): SearchSchemaInterface
    {
        return $this->standaloneInstance;
    }

}