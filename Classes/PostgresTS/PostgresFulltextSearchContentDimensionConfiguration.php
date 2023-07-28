<?php

namespace Sandstorm\KISSearch\PostgresTS;

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Exception\InvalidConfigurationTypeException;
use Sandstorm\KISSearch\InvalidConfigurationException;

class PostgresFulltextSearchContentDimensionConfiguration
{

    private readonly string $dimensionName;

    private readonly array $dimensionValueMapping;

    /**
     * @param string $dimensionName
     * @param array $dimensionValueMapping
     */
    public function __construct(string $dimensionName, array $dimensionValueMapping)
    {
        $this->dimensionName = $dimensionName;
        $this->dimensionValueMapping = $dimensionValueMapping;
    }

    /**
     * @param ConfigurationManager $configurationManager
     * @param bool $validateContentDimensionNameAvailable
     * @return PostgresFulltextSearchContentDimensionConfiguration
     * @throws InvalidConfigurationTypeException
     */
    public static function fromSettings(ConfigurationManager $configurationManager, bool $validateContentDimensionNameAvailable): PostgresFulltextSearchContentDimensionConfiguration
    {
        return new PostgresFulltextSearchContentDimensionConfiguration(
            self::getDimensionNameSetting($configurationManager, $validateContentDimensionNameAvailable),
            self::getDimensionValueMappingSetting($configurationManager)
        );
    }

    /**
     * @param ConfigurationManager $configurationManager
     * @param bool $validateContentDimensionNameAvailable
     * @return string
     * @throws InvalidConfigurationTypeException
     */
    public static function getDimensionNameSetting(ConfigurationManager $configurationManager, bool $validateContentDimensionNameAvailable): string
    {
        $dimensionName = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.KISSearch.postgres.contentDimension.dimensionName'
        );
        if (!is_string($dimensionName)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid configuration 'Sandstorm.KISSearch.postgres.contentDimension.dimensionName'; expected type string but was %s (value: %s)",
                    gettype($dimensionName),
                    print_r($dimensionName, true)
                ),
                1690486058
            );
        }
        if ($validateContentDimensionNameAvailable) {
            // check if there is actually a content dimension with that name
            $availableContentDimensions = $configurationManager->getConfiguration(
                ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                'Neos.ContentRepository.contentDimensions'
            );
            if (!is_array($availableContentDimensions)) {
                throw new InvalidConfigurationException(
                    sprintf(
                        "Invalid configuration 'Sandstorm.KISSearch.postgres.contentDimension.dimensionName'; dimension name '%s' is configured, but no content dimensions are available",
                        $dimensionName
                    ),
                    1690486587
                );
            }
            if (!array_key_exists($dimensionName, $availableContentDimensions)) {
                throw new InvalidConfigurationException(
                    sprintf(
                        "Invalid configuration 'Sandstorm.KISSearch.postgres.contentDimension.dimensionName'; dimension name '%s' is configured, but that content dimension is not available",
                        $dimensionName
                    ),
                    1690486661
                );
            }
        }
        return $dimensionName;
    }

    /**
     * @param ConfigurationManager $configurationManager
     * @return array
     * @throws InvalidConfigurationTypeException
     */
    public static function getDimensionValueMappingSetting(ConfigurationManager $configurationManager): array
    {
        $dimensionValueMapping = $configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
            'Sandstorm.KISSearch.postgres.contentDimension.dimensionValueMapping'
        );
        if (!is_array($dimensionValueMapping)) {
            throw new InvalidConfigurationException(
                sprintf(
                    "Invalid configuration 'Sandstorm.KISSearch.postgres.contentDimension.dimensionValueMapping'; expected type array but was %s (value: %s)",
                    gettype($dimensionValueMapping),
                    print_r($dimensionValueMapping, true)
                ),
                1690486828
            );
        }
        return $dimensionValueMapping;
    }

    public function validateAvailableTsConfigs(array $availableTsConfigs): void
    {
        foreach ($this->dimensionValueMapping as $dimensionValue => $tsConfig) {
            if (!in_array($tsConfig, $availableTsConfigs)) {
                throw new InvalidConfigurationException(
                    sprintf(
                        "Invalid 'Sandstorm.KISSearch.postgres.contentDimension.dimensionValueMapping.%s' configuration value; the postgres tsConfig '%s' is not available, use one of %s",
                        $dimensionValue,
                        $tsConfig,
                        implode(', ', $availableTsConfigs)
                    ),
                    1690485042
                );
            }
        }
    }

    /**
     * @return string
     */
    public function getDimensionName(): string
    {
        return $this->dimensionName;
    }

    /**
     * @return array
     */
    public function getDimensionValueMapping(): array
    {
        return $this->dimensionValueMapping;
    }

}
