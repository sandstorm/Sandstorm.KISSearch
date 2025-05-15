<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Schema\Configuration;

readonly class SearchSchemaConfiguration
{
    /**
     * @param array<string, string> $schemaClasses
     */
    public function __construct(
        private array $schemaClasses
    )
    {
        // TODO validate
    }

    public static function fromConfigurationArray(array $schemasConfig): self
    {
        // pure validation
        $schemas = [];
        foreach ($schemasConfig as $schemaIdentifier => $schemaClass) {
            if (!is_string($schemaIdentifier)) {
                throw new \RuntimeException("Invalid search schemas configuration 'schemas.$schemaIdentifier'; key must be a string but was: " . gettype($schemaIdentifier));
            }
            if (!is_string($schemaClass)) {
                throw new \RuntimeException("Invalid search schemas configuration 'schemas.$schemaIdentifier'; value must be a string but was: " . gettype($schemaClass));
            }
            $schemas[$schemaIdentifier] = $schemaClass;
        }

        return new self($schemas);
    }

    /**
     * @return array<string, string>
     */
    public function getSchemaClasses(): array
    {
        return $this->schemaClasses;
    }
}