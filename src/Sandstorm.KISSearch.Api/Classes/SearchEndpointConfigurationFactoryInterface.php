<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api;

use Sandstorm\KISSearch\Api\Query\Configuration\SearchEndpointConfiguration;

interface SearchEndpointConfigurationFactoryInterface
{

    function createEndpointConfiguration(string $endpointIdentifier): SearchEndpointConfiguration;

}