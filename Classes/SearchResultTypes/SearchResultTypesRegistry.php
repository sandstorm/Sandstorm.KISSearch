<?php

namespace Sandstorm\KISSearch\SearchResultTypes;


use Neos\Flow\Annotations\InjectConfiguration;
use Neos\Flow\Annotations\Scope;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

#[Scope('singleton')]
class SearchResultTypesRegistry
{

    #[InjectConfiguration('searchResultTypes')]
    protected array $searchResultTypesConfiguration;

    // constructor injected
    private readonly ObjectManagerInterface $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * @return SearchResultTypeInterface[]
     */
    public function getConfiguredSearchResultTypes(): array
    {
        $result = [];

        if (empty($this->searchResultTypesConfiguration)) {
            throw new InvalidConfigurationException('No search result types configured in settings', 1689927933);
        }

        foreach ($this->searchResultTypesConfiguration as $typeNameFromConfigurationKey => $searchResultTypeClassName) {
            if (!$this->objectManager->has($searchResultTypeClassName)) {
                throw new InvalidConfigurationException(
                    "Invalid search result type class '$searchResultTypeClassName'; class must be an injectable flow service",
                    1689928083
                );
            }
            $searchResultTypeInstance = $this->objectManager->get($searchResultTypeClassName);
            if ($searchResultTypeInstance instanceof SearchResultTypeInterface) {
                $typeNameFromInstance = $searchResultTypeInstance->getName()->getName();
                if ($typeNameFromInstance !== $typeNameFromConfigurationKey) {
                    $methodDescription = sprintf('%s::getName()', $searchResultTypeClassName);
                    throw new InvalidConfigurationException(
                        "Invalid search result type configuration for '$searchResultTypeClassName';"
                          . " configuration key '$typeNameFromConfigurationKey' must match type name '$typeNameFromInstance' returned from $methodDescription",
                        1689929404
                    );
                }
                $result[$typeNameFromInstance] = $searchResultTypeInstance;
            } else {
                $expectedClassName = SearchResultTypeInterface::class;
                throw new InvalidConfigurationException(
                    "Invalid search result type class '$searchResultTypeClassName'; class must implement the interface '$expectedClassName'",
                    1689928083
                );
            }
        }
        // we sort for deterministic SQL query part order
        ksort($result);
        return $result;
    }


}
