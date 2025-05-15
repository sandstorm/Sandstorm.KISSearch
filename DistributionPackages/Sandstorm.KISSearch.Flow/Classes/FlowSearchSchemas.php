<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Flow;

use Neos\Flow\Annotations\InjectConfiguration;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Api\Schema\Configuration\SearchSchemaConfiguration;

#[Scope('singleton')]
class FlowSearchSchemas
{

    #[InjectConfiguration(path: 'schemas', package: 'Sandstorm.KISSearch')]
    protected ?array $schemas;

    public function getSchemaConfiguration(): SearchSchemaConfiguration
    {
        if ($this->schemas === null) {
            throw new InvalidConfigurationException('No KISSearch schemas configured.');
        }
        try {
            return SearchSchemaConfiguration::fromConfigurationArray($this->schemas);
        } catch (\RuntimeException $e) {
            throw new InvalidConfigurationException('Invalid KISSearch schemas configuration: ' . $e->getMessage(), 0, $e);
        }
    }

}