<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\FrameworkAbstraction;

use Sandstorm\KISSearch\Api\Schema\SearchSchemaInterface;

readonly class DefaultSchemaObjectInstanceProvider implements SchemaObjectInstanceProvider
{
    /**
     * @param array<string, SearchSchemaInterface> $instances
     */
    public function __construct(
        private array $instances
    )
    {
    }

    function getSearchSchemaInstance(string $className): SearchSchemaInterface
    {
        $instance = $this->instances[$className] ?? null;
        if ($instance === null) {
            throw new UnknownObjectException("There is no Schema instance found for class name '$className'");
        }
        return $instance;
    }
}