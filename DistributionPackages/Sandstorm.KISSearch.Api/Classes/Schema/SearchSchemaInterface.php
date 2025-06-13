<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Schema;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;

/**
 * TODO comment
 */
interface SearchSchemaInterface
{

    /**
     * @param DatabaseType $databaseType
     * @param string $schemaIdentifier
     * @param array $options
     * @return array<string>
     */
    function createSchema(DatabaseType $databaseType, string $schemaIdentifier, array $options): array;

    /**
     * @param DatabaseType $databaseType
     * @param string $schemaIdentifier
     * @param array $options
     * @return array<string>
     */
    function dropSchema(DatabaseType $databaseType, string $schemaIdentifier, array $options): array;

}