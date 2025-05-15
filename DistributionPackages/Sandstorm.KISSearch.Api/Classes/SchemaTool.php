<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\FrameworkAbstraction\SchemaObjectInstanceProvider;
use Sandstorm\KISSearch\Api\Schema\Configuration\SearchSchemaConfiguration;

class SchemaTool
{

    public static function createSchemaSql(
        DatabaseType $databaseType,
        SearchSchemaConfiguration $configuration,
        SchemaObjectInstanceProvider $instanceProvider
    ): string
    {
        $schemaSqlStatements = [
            '-- ###################################',
            '-- ###   create KISSearch schema   ###',
            '-- ###################################',
            "--  * database type: $databaseType->value",
            '-- START OF SCHEMA LOOP'
        ];

        $schemaInstancesByClassName = [];
        foreach ($configuration->getSchemaClasses() as $schemaIdentifier => $schemaClass) {
            $schemaSqlStatements[] = "--  * START OF CREATE SCHEMA '$schemaIdentifier'";
            if (!array_key_exists($schemaClass, $schemaInstancesByClassName)) {
                $schemaInstancesByClassName[$schemaClass] = $instanceProvider->getSearchSchemaInstance($schemaClass);
            }
            foreach ($schemaInstancesByClassName[$schemaClass]->createSchema($databaseType) as $sqlStatement) {
                $schemaSqlStatements[] = $sqlStatement;
            }
            $schemaSqlStatements[] = "--  * END OF CREATE SCHEMA '$schemaIdentifier'";
        }

        return implode("\n", $schemaSqlStatements);
    }

    public static function dropSchemaSql(
        DatabaseType $databaseType,
        SearchSchemaConfiguration $configuration,
        SchemaObjectInstanceProvider $instanceProvider
    ): string
    {
        $schemaSqlStatements = [
            '-- ###################################',
            '-- ###    drop KISSearch schema    ###',
            '-- ###################################',
            "--  * database type: $databaseType->value",
            '-- START OF SCHEMA LOOP'
        ];

        $schemaInstancesByClassName = [];
        foreach ($configuration->getSchemaClasses() as $schemaIdentifier => $schemaClass) {
            $schemaSqlStatements[] = "--  * START OF DROP SCHEMA '$schemaIdentifier'";
            if (!array_key_exists($schemaClass, $schemaInstancesByClassName)) {
                $schemaInstancesByClassName[$schemaClass] = $instanceProvider->getSearchSchemaInstance($schemaClass);
            }
            foreach ($schemaInstancesByClassName[$schemaClass]->dropSchema($databaseType) as $sqlStatement) {
                $schemaSqlStatements[] = $sqlStatement;
            }
            $schemaSqlStatements[] = "--  * END OF DROP SCHEMA '$schemaIdentifier'";
        }

        return implode("\n", $schemaSqlStatements);
    }

}