<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Flow;

use Neos\Flow\Annotations\Inject;
use Neos\Flow\Annotations\InjectConfiguration;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Sandstorm\KISSearch\Api\FrameworkAbstraction\QueryObjectInstanceProvider;
use Sandstorm\KISSearch\Api\FrameworkAbstraction\SchemaObjectInstanceProvider;
use Sandstorm\KISSearch\Api\Query\ResultFilterInterface;
use Sandstorm\KISSearch\Api\Query\SearchSourceInterface;
use Sandstorm\KISSearch\Api\Query\TypeAggregatorInterface;
use Sandstorm\KISSearch\Api\Schema\SearchSchemaInterface;
use Sandstorm\KISSearch\Neos\Query\NeosContentQuery;

#[Scope('singleton')]
class FlowCDIObjectInstanceProvider implements QueryObjectInstanceProvider, SchemaObjectInstanceProvider
{

    #[InjectConfiguration(path: 'query.searchSources', package: 'Sandstorm.KISSearch')]
    protected array $searchSources;

    #[InjectConfiguration(path: 'query.resultFilters', package: 'Sandstorm.KISSearch')]
    protected array $resultFilters;

    #[InjectConfiguration(path: 'query.typeAggregators', package: 'Sandstorm.KISSearch')]
    protected array $typeAggregators;

    #[Inject]
    protected ObjectManagerInterface $objectManager;


    function getSearchSourceInstance(string $identifier): SearchSourceInterface
    {
        $className = self::getServiceClassName($identifier, $this->searchSources, 'query.searchSources');
        return $this->objectManager->get($className);
    }

    function getResultFilterInstance(string $identifier): ResultFilterInterface
    {
        $className = self::getServiceClassName($identifier, $this->resultFilters, 'query.resultFilters');
        return $this->objectManager->get($className);
    }

    function getTypeAggregatorInstance(string $identifier): TypeAggregatorInterface
    {
        $className = self::getServiceClassName($identifier, $this->typeAggregators, 'query.typeAggregators');
        return $this->objectManager->get($className);
    }

    function getSearchSchemaInstance(string $className): SearchSchemaInterface
    {
        return $this->objectManager->get($className);
    }

    private static function getServiceClassName(string $identifier, array $configuration, string $errorDescription): string
    {
        if (!array_key_exists($identifier, $configuration)) {
            throw new InvalidConfigurationException("Configuration key '$errorDescription.$identifier' not found in KISSearch query configuration");
        }
        $serviceDeclaration = $configuration[$identifier];
        if (!is_array($serviceDeclaration)) {
            throw new InvalidConfigurationException("Invalid KISSearch query configuration '$errorDescription.$identifier'; expected array but got " . gettype($serviceDeclaration));
        }
        $serviceClassName = $serviceDeclaration['class'] ?? null;
        if (!is_string($serviceClassName) || strlen(trim($serviceClassName)) === 0) {
            throw new InvalidConfigurationException("Invalid KISSearch query configuration '$errorDescription.$identifier.class'; expected string but got " . gettype($serviceClassName));
        }
        return $serviceClassName;
    }

}