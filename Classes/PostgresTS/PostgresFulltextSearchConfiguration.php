<?php

namespace Sandstorm\KISSearch\PostgresTS;

use Neos\Flow\Annotations\Proxy;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Sandstorm\KISSearch\InvalidConfigurationException;

#[Proxy(false)]
class PostgresFulltextSearchConfiguration
{

    private readonly string $defaultTsConfig;

    private readonly PostgresFulltextSearchMode $mode;

    private readonly PostgresFulltextSearchContentDimensionConfiguration $contentDimensionConfiguration;

    /**
     * @param string $defaultTsConfig
     * @param PostgresFulltextSearchMode $mode
     * @param PostgresFulltextSearchContentDimensionConfiguration $contentDimensionConfiguration
     */
    public function __construct(string $defaultTsConfig, PostgresFulltextSearchMode $mode, PostgresFulltextSearchContentDimensionConfiguration $contentDimensionConfiguration)
    {
        $this->defaultTsConfig = $defaultTsConfig;
        $this->mode = $mode;
        $this->contentDimensionConfiguration = $contentDimensionConfiguration;
    }

    /**
     * @param ConfigurationManager $configurationManager
     * @return PostgresFulltextSearchConfiguration
     * @throws InvalidConfigurationTypeException
     */
    public static function fromSettings(ConfigurationManager $configurationManager): PostgresFulltextSearchConfiguration
    {
        $mode = self::getModeSetting($configurationManager);
        return new PostgresFulltextSearchConfiguration(
            self::getDefaultTsConfigSetting($configurationManager),
            $mode,
            PostgresFulltextSearchContentDimensionConfiguration::fromSettings(
                $configurationManager,
                // only validate if we want to use content dimension mode
                $mode === PostgresFulltextSearchMode::CONTENT_DIMENSION
            )
        );
    }

    /**
     * @param ConfigurationManager $configurationManager
     * @return string
     * @throws InvalidConfigurationTypeException
     */
    public static function getDefaultTsConfigSetting(ConfigurationManager $configurationManager): string
    {
        $defaultTsConfig = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.KISSearch.postgres.defaultTsConfig'
        );
        if (!is_string($defaultTsConfig)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid configuration 'Sandstorm.KISSearch.postgres.defaultTsConfig'; expected type string but was %s (value: %s)",
                    gettype($defaultTsConfig),
                    print_r($defaultTsConfig, true)
                ),
                1690485271
            );
        }
        return $defaultTsConfig;
    }

    /**
     * @param ConfigurationManager $configurationManager
     * @return PostgresFulltextSearchMode
     * @throws InvalidConfigurationTypeException
     */
    public static function getModeSetting(ConfigurationManager $configurationManager): PostgresFulltextSearchMode
    {
        $modeAsString = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.KISSearch.postgres.mode'
        );
        if (!is_string($modeAsString)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid configuration 'Sandstorm.KISSearch.postgres.mode'; expected type string but was %s (value: %s)",
                    gettype($modeAsString),
                    print_r($modeAsString, true)
                ),
                1690485355
            );
        }
        $mode = PostgresFulltextSearchMode::tryFrom($modeAsString);
        if ($mode === null) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid configuration 'Sandstorm.KISSearch.postgres.mode'; expected one of %s but was '%s'",
                    implode(', ', array_map(function ($availableMode) {
                        return $availableMode->value;
                    }, PostgresFulltextSearchMode::cases())),
                    $modeAsString
                ),
                1690485355
            );
        }
        return $mode;
    }

    public function validateAvailableTsConfigs(array $availableTsConfigs): void
    {
        if (!in_array($this->defaultTsConfig, $availableTsConfigs)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid 'Sandstorm.KISSearch.postgres.defaultTsConfig' configuration value; the postgres tsConfig '%s' is not available, use one of %s",
                    $this->defaultTsConfig,
                    implode(', ', $availableTsConfigs)
                ),
                1690484896
            );
        }
        if ($this->mode === PostgresFulltextSearchMode::CONTENT_DIMENSION) {
            $this->contentDimensionConfiguration->vaidateAvailableTsConfigs($availableTsConfigs);
        }
    }

    /**
     * @return string
     */
    public function getDefaultTsConfig(): string
    {
        return $this->defaultTsConfig;
    }

    /**
     * @return PostgresFulltextSearchMode
     */
    public function getMode(): PostgresFulltextSearchMode
    {
        return $this->mode;
    }

    /**
     * @return PostgresFulltextSearchContentDimensionConfiguration
     */
    public function getContentDimensionConfiguration(): PostgresFulltextSearchContentDimensionConfiguration
    {
        return $this->contentDimensionConfiguration;
    }

}
