<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api;

use Sandstorm\KISSearch\Api\DBAbstraction\DatabaseType;
use Sandstorm\KISSearch\Api\FrameworkAbstraction\SchemaObjectInstanceProvider;
use Sandstorm\KISSearch\Api\Schema\Configuration\SearchSchemasConfiguration;

class SchemaTool
{

    public static function createSchemaSql(
        DatabaseType $databaseType,
        SearchSchemasConfiguration $configuration,
        SchemaObjectInstanceProvider $instanceProvider
    ): string
    {
        $schemaSqlStatements = [
            '-- ###################################',
            '-- ###   create KISSearch schema   ###',
            '-- ###################################',
            "--  * database type: $databaseType->value",
        ];

        $schemaInstancesByClassName = [];
        foreach ($configuration->getSchemaConfigurations() as $schemaIdentifier => $schemaConfig) {
            $schemaSqlStatements[] = "--  * START OF CREATE SCHEMA '$schemaIdentifier'";
            if (!array_key_exists($schemaConfig->getSchemaClass(), $schemaInstancesByClassName)) {
                $schemaInstancesByClassName[$schemaConfig->getSchemaClass()] = $instanceProvider->getSearchSchemaInstance($schemaConfig->getSchemaClass());
            }
            foreach ($schemaInstancesByClassName[$schemaConfig->getSchemaClass()]->createSchema($databaseType, $schemaIdentifier, $schemaConfig->getOptions()) as $sqlStatement) {
                $schemaSqlStatements[] = $sqlStatement;
            }
            $schemaSqlStatements[] = "--  * END OF CREATE SCHEMA '$schemaIdentifier'";
        }

        return implode("\n", $schemaSqlStatements);
    }

    public static function dropSchemaSql(
        DatabaseType $databaseType,
        SearchSchemasConfiguration $configuration,
        SchemaObjectInstanceProvider $instanceProvider
    ): string
    {
        $schemaSqlStatements = [
            '-- ###################################',
            '-- ###    drop KISSearch schema    ###',
            '-- ###################################',
            "--  * database type: $databaseType->value",
        ];

        $schemaInstancesByClassName = [];
        foreach ($configuration->getSchemaConfigurations() as $schemaIdentifier => $schemaConfig) {
            $schemaSqlStatements[] = "--  * START OF DROP SCHEMA '$schemaIdentifier'";
            if (!array_key_exists($schemaConfig->getSchemaClass(), $schemaInstancesByClassName)) {
                $schemaInstancesByClassName[$schemaConfig->getSchemaClass()] = $instanceProvider->getSearchSchemaInstance($schemaConfig->getSchemaClass());
            }
            foreach ($schemaInstancesByClassName[$schemaConfig->getSchemaClass()]->dropSchema($databaseType, $schemaIdentifier, $schemaConfig->getOptions()) as $sqlStatement) {
                $schemaSqlStatements[] = $sqlStatement;
            }
            $schemaSqlStatements[] = "--  * END OF DROP SCHEMA '$schemaIdentifier'";
        }

        return implode("\n", $schemaSqlStatements);
    }

    public static function refreshSearchDependenciesSql(
        DatabaseType $databaseType,
        SearchSchemasConfiguration $configuration,
        SchemaObjectInstanceProvider $instanceProvider,
        ?string $schemaFilter
    ): string
    {
        $refreshSqlStatements = [
            '-- ############################################',
            '-- ###    refresh KISSearch dependencies    ###',
            '-- ############################################',
            "--  * database type: $databaseType->value",
        ];
        if ($schemaFilter !== null) {
            // single mode
            $schemaConfig = $configuration->getSchemaConfigurations()[$schemaFilter]
                ?? throw new \RuntimeException("Schema filter '$schemaFilter' does not point to an existing configuration");
            $refresherInstance = $instanceProvider->getDependencyRefresherInstance($schemaConfig->getRefresherClass());
            foreach ($refresherInstance->refreshSearchDependencies($databaseType, $schemaConfig->getSchemaIdentifier(), $schemaConfig->getOptions()) as $sqlStatement) {
                $refreshSqlStatements[] = $sqlStatement;
            }
        } else {
            // all schemas
            $refresherInstancesByClassName = [];
            foreach ($configuration->getSchemaConfigurations() as $schemaIdentifier => $schemaConfig) {
                $refreshSqlStatements[] = "--  * START OF REFRESH SCHEMA '$schemaIdentifier'";
                if (!array_key_exists($schemaConfig->getRefresherClass(), $refresherInstancesByClassName)) {
                    $refresherInstancesByClassName[$schemaConfig->getRefresherClass()] = $instanceProvider->getDependencyRefresherInstance($schemaConfig->getRefresherClass());
                }
                foreach ($refresherInstancesByClassName[$schemaConfig->getRefresherClass()]->refreshSearchDependencies($databaseType, $schemaIdentifier, $schemaConfig->getOptions()) as $sqlStatement) {
                    $refreshSqlStatements[] = $sqlStatement;
                }
                $refreshSqlStatements[] = "--  * END OF REFRESH SCHEMA '$schemaIdentifier'";
            }
        }


        return implode("\n", $refreshSqlStatements);
    }

}