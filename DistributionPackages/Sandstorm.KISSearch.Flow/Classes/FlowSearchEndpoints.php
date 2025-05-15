<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Flow;

use Neos\Flow\Annotations\InjectConfiguration;
use Neos\Flow\Annotations\Scope;
use Sandstorm\KISSearch\Api\Query\Configuration\SearchEndpointConfiguration;

#[Scope('singleton')]
class FlowSearchEndpoints {

    #[InjectConfiguration(path: 'query.endpoints', package: 'Sandstorm.KISSearch')]
    protected ?array $endpoints;

    public function getEndpointConfiguration(string $endpointIdentifier): SearchEndpointConfiguration
    {
        if ($this->endpoints === null) {
            throw new InvalidConfigurationException('No KISSearch query endpoints configured.');
        }
        try {
            return SearchEndpointConfiguration::fromConfigurationArray($endpointIdentifier, $this->endpoints);
        } catch (\RuntimeException $e) {
            throw new InvalidConfigurationException('Invalid KISSearch query endpoints configuration: ' . $e->getMessage(), 0, $e);
        }
    }

}