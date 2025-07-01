<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Schema\Configuration;

readonly class SearchSchemasConfiguration
{
    /**
     * @param array<string, SearchSchemaConfiguration> $schemaConfigurations
     */
    public function __construct(
        private array $schemaConfigurations
    )
    {
        // TODO validate
    }

    public static function fromConfigurationArray(array $schemasConfig): self
    {
        // pure validation
        $schemas = [];
        foreach ($schemasConfig as $schemaIdentifier => $schemaConfig) {
            if (!is_string($schemaIdentifier)) {
                throw new \RuntimeException("Invalid search schemas configuration 'schemas.$schemaIdentifier'; key must be a string but was: " . gettype($schemaIdentifier));
            }
            if (!is_array($schemaConfig)) {
                throw new \RuntimeException("Invalid search schemas configuration 'schemas.$schemaIdentifier'; value must be an array but was: " . gettype($schemaConfig));
            }
            $schemas[$schemaIdentifier] = SearchSchemaConfiguration::fromConfigurationArray($schemaIdentifier, $schemaConfig);
        }

        return new self($schemas);
    }

    /**
     * @return array<string, SearchSchemaConfiguration>
     */
    public function getSchemaConfigurations(): array
    {
        return $this->schemaConfigurations;
    }

    /**
     * @return array<string>
     */
    public function getAllSchemaIds(): array
    {
        return array_keys($this->schemaConfigurations);
    }
}