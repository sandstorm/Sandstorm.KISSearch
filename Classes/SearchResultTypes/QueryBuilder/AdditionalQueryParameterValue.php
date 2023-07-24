<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\InvalidAdditionalParameterException;

#[Proxy(false)]
class AdditionalQueryParameterValue
{

    private readonly mixed $parameterValue;

    private readonly AdditionalQueryParameterDefinition $parameterDefinition;

    /**
     * @param mixed $parameterValue
     * @param AdditionalQueryParameterDefinition $parameterDefinition
     */
    function __construct(mixed $parameterValue, AdditionalQueryParameterDefinition $parameterDefinition)
    {
        $parameterName = $parameterDefinition->getParameterName();
        if ($parameterDefinition->isRequired() && $parameterValue === null) {
            throw new InvalidAdditionalParameterException(
                "Additional parameter '$parameterName' is required, but null value was given",
                1689982704
            );
        }
        if ($parameterValue !== null) {
            $actualParameterType = gettype($parameterValue);
            if ($parameterDefinition->getParameterType() === AdditionalQueryParameterDefinition::TYPE_STRING && !is_string($parameterValue)) {
                throw new InvalidAdditionalParameterException(
                    "Additional parameter '$parameterName' is declared to be of type string, " .
                        "but was of type '$actualParameterType' (value: $parameterValue)",
                    1689984905
                );
            }
            if ($parameterDefinition->getParameterType() === AdditionalQueryParameterDefinition::TYPE_INTEGER && !is_integer($parameterValue)) {
                throw new InvalidAdditionalParameterException(
                    "Additional parameter '$parameterName' is declared to be of type integer, " .
                        "but was of type '$actualParameterType' (value: $parameterValue)",
                    1689984955
                );
            }
            if ($parameterDefinition->getParameterType() === AdditionalQueryParameterDefinition::TYPE_FLOAT && !is_float($parameterValue)) {
                throw new InvalidAdditionalParameterException(
                    "Additional parameter '$parameterName' is declared to be of type float, " .
                        "but was of type '$actualParameterType' (value: $parameterValue)",
                    1689984977
                );
            }
            if ($parameterDefinition->getParameterType() === AdditionalQueryParameterDefinition::TYPE_BOOLEAN && !is_bool($parameterValue)) {
                throw new InvalidAdditionalParameterException(
                    "Additional parameter '$parameterName' is declared to be of type boolean, " .
                        "but was of type '$actualParameterType' (value: $parameterValue)",
                    1689985000
                );
            }
        }
        $this->parameterValue = $parameterValue;
        $this->parameterDefinition = $parameterDefinition;
    }

    /**
     * @return mixed
     */
    public function getParameterValue(): mixed
    {
        return $this->parameterValue;
    }

    /**
     * @return AdditionalQueryParameterDefinition
     */
    public function getParameterDefinition(): AdditionalQueryParameterDefinition
    {
        return $this->parameterDefinition;
    }

}
