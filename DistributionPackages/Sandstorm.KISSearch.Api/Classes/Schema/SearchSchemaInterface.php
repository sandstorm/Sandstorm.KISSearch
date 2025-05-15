<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Schema;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;

interface SearchSchemaInterface
{

    /**
     * @param DatabaseType $databaseType
     * @return array<string>
     */
    function createSchema(DatabaseType $databaseType): array;

    /**
     * @param DatabaseType $databaseType
     * @return array<string>
     */
    function dropSchema(DatabaseType $databaseType): array;

}