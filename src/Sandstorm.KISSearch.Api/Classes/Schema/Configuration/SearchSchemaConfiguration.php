<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Schema\Configuration;

readonly class SearchSchemaConfiguration
{
    /**
     * @param string $schemaIdentifier
     * @param string $schemaClass
     * @param ?string $refresherClass
     * @param array<string, mixed> $options
     */
    public function __construct(
        private string $schemaIdentifier,
        private string $schemaClass,
        private ?string $refresherClass,
        private array $options
    )
    {
        // TODO validate
    }

    public static function fromConfigurationArray(string $identifier, array $schemaConfig): self
    {
        $schemaClass = $schemaConfig['class'];
        if (!is_string($schemaClass) || strlen(trim($schemaClass)) === 0) {
            throw new \RuntimeException("Invalid search schema configuration '...schemas.$identifier.class'; value must be a string but was: " . gettype($schemaClass));
        }
        $refresherClass = $schemaConfig['refresher'] ?? null;
        if ($refresherClass !== null && (!is_string($refresherClass) || strlen(trim($refresherClass)) === 0)) {
            throw new \RuntimeException("Invalid search schema configuration '...schemas.$identifier.refresher'; value must be null or a string but was: " . gettype($refresherClass));
        }
        $options = $schemaConfig['options'] ?? [];
        if (!is_array($options)) {
            throw new \RuntimeException("Invalid search schema configuration '...schemas.$identifier.options'; value must be an array but was: " . gettype($options));
        }

        return new self(
            $identifier,
            $schemaClass,
            $refresherClass,
            $options
        );
    }

    public function getSchemaIdentifier(): string
    {
        return $this->schemaIdentifier;
    }

    public function getSchemaClass(): string
    {
        return $this->schemaClass;
    }

    public function getRefresherClass(): ?string
    {
        return $this->refresherClass;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

}