<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

use Neos\Flow\Annotations\Proxy;
use Neos\Flow\Configuration\ConfigurationManager;
use Sandstorm\KISSearch\InvalidConfigurationException;

/**
 * @Proxy(false)
 */
enum DatabaseType: string
{
    case MYSQL = 'MySQL';
    case MARIADB = 'MariaDB';
    case POSTGRES = 'PostgreSQL';

    public static function detectDatabase(ConfigurationManager $configurationManager): DatabaseType
    {
        $configuredDatabaseType = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.KISSearch.databaseType'
        );
        if ($configuredDatabaseType === null) {
            throw new InvalidConfigurationException(
                sprintf(
                    "No database type configured. Please configure it in your Settings.yaml using the key"
                             . " 'Sandstorm.KISSearch.databaseType'; possible values: %s",
                    implode(', ', array_map(function (DatabaseType $databaseType) {
                        return $databaseType->value;
                    }, DatabaseType::cases()))
                ),
                1690133019
            );
        }
        if (!is_string($configuredDatabaseType)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Configuration 'Sandstorm.KISSearch.databaseType' must be a string; but was: %s",
                    gettype($configuredDatabaseType)
                ),
                1690133261
            );
        }
        $databaseType = DatabaseType::tryFrom($configuredDatabaseType);
        if ($databaseType === null) {
            throw new UnsupportedDatabaseException(
                sprintf(
                    "Configured database type '%s' is not supported by Sandstorm.KISSearch; supported types: %s",
                    $configuredDatabaseType,
                    implode(', ', array_map(function(DatabaseType $t) {
                        return $t->value;
                    }, DatabaseType::cases()))
                ),
                1690134239
            );
        }
        $expectedDriverName = match ($databaseType) {
            self::MYSQL, self::MARIADB => 'pdo_mysql',
            self::POSTGRES => 'pdo_pgsql',
            default => throw new UnsupportedDatabaseException(
                sprintf(
                    "Configured database type '%s' is not supported by Sandstorm.KISSearch; supported types: %s",
                    $configuredDatabaseType,
                    implode(', ', array_map(function(DatabaseType $t) {
                        return $t->value;
                    }, DatabaseType::cases()))
                ),
                1689629845
            )
        };
        $actualDriverName = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Flow.persistence.backendOptions.driver'
        );
        if ($actualDriverName !== $expectedDriverName) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Inconsistent database type and pdo driver; the configured database type '%s' requires the "
                            . "pdo driver '%s'; but configured is '%s'",
                    $configuredDatabaseType,
                    $expectedDriverName,
                    $actualDriverName
                ),
                1690134494
            );
        }
        return $databaseType;
    }
}
