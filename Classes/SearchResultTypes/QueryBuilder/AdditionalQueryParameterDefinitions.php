<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use ArrayObject;
use Neos\Flow\Annotations\Proxy;

/**
 * @Proxy(false)
 */
class AdditionalQueryParameterDefinitions extends ArrayObject
{

    /**
     * @param AdditionalQueryParameterDefinition[] $values
     */
    private function __construct(array $values)
    {
        parent::__construct($values);
    }

    public static function create(AdditionalQueryParameterDefinition ...$values): AdditionalQueryParameterDefinitions
    {
        return new AdditionalQueryParameterDefinitions($values);
    }
}
