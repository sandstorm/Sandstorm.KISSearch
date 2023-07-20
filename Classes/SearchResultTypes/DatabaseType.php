<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

use Neos\Flow\Annotations\Proxy;
use Neos\Flow\Configuration\ConfigurationManager;

#[Proxy(false)]
enum DatabaseType
{
    case MYSQL;
    case POSTGRES;

    public static function detectDatabase(ConfigurationManager $configurationManager): DatabaseType
    {
        $dbDriver = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Neos.Flow.persistence.backendOptions.driver'
        );
        return match ($dbDriver) {
            'pdo_mysql' => DatabaseType::MYSQL,
            'pdo_pgsql' => DatabaseType::POSTGRES,
            default => throw new UnsupportedDatabaseException(
                "Database driver '$dbDriver' is not supported by Sandstorm.KISSearch",
                1689629845
            ),
        };
    }
}
