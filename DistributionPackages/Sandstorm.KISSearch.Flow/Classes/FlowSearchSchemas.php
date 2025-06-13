<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Flow;

use Neos\Flow\Annotations\InjectConfiguration;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Api\Schema\Configuration\SearchSchemasConfiguration;

#[Scope('singleton')]
class FlowSearchSchemas
{

    #[InjectConfiguration(path: 'schemas', package: 'Sandstorm.KISSearch')]
    protected ?array $schemas;

    public function getSchemaConfiguration(): SearchSchemasConfiguration
    {
        if ($this->schemas === null) {
            throw new InvalidConfigurationException('No KISSearch schemas configured.');
        }
        try {
            return SearchSchemasConfiguration::fromConfigurationArray($this->schemas);
        } catch (\RuntimeException $e) {
            throw new InvalidConfigurationException('Invalid KISSearch schemas configuration: ' . $e->getMessage(), 0, $e);
        }
    }

}