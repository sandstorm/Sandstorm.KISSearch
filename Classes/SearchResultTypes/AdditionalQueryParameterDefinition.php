<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class AdditionalQueryParameterDefinition
{

    public const TYPE_STRING = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_BOOLEAN = 'boolean';

    private readonly string $parameterName;
    private readonly string $parameterType;
    private readonly SearchResultTypeName $searchResultTypeName;
    private readonly bool $required;

    /**
     * @param string $parameterName
     * @param string $parameterType
     * @param SearchResultTypeName $searchResultTypeName
     * @param bool $required
     */
    private function __construct(string $parameterName, string $parameterType, SearchResultTypeName $searchResultTypeName, bool $required)
    {
        if (!preg_match('/\w+/', $parameterName)) {
            throw new InvalidAdditionalParameterException(
                "Additional parameter name must contain only word characters; but was '$parameterName'",
                1689982704
            );
        }
        if (!in_array($parameterType, [self::TYPE_STRING, self::TYPE_INTEGER, self::TYPE_FLOAT, self::TYPE_BOOLEAN])) {
            throw new InvalidAdditionalParameterException(
                sprintf(
                    "Type of additional parameter '%s' must be one of '%s', '%s', '%s' or '%s'; but was '%s'",
                    $parameterName,
                    self::TYPE_STRING, self::TYPE_INTEGER, self::TYPE_FLOAT, self::TYPE_BOOLEAN,
                    $parameterType
                ),
                1689984588
            );
        }
        $this->parameterName = $parameterName;
        $this->parameterType = $parameterType;
        $this->searchResultTypeName = $searchResultTypeName;
        $this->required = $required;
    }

    public static function optional(string $parameterName, string $parameterType, SearchResultTypeName $searchResultTypeName): AdditionalQueryParameterDefinition
    {
        return new AdditionalQueryParameterDefinition($parameterName, $parameterType, $searchResultTypeName, false);
    }

    public static function required(string $parameterName, string $parameterType, SearchResultTypeName $searchResultTypeName): AdditionalQueryParameterDefinition
    {
        return new AdditionalQueryParameterDefinition($parameterName, $parameterType, $searchResultTypeName, true);
    }

    public function withParameterValue(mixed $parameterValue): AdditionalQueryParameterValue
    {
        return new AdditionalQueryParameterValue($parameterValue, $this);
    }

    /**
     * @return string
     */
    public function getParameterName(): string
    {
        return $this->parameterName;
    }

    /**
     * @return string
     */
    public function getParameterType(): string
    {
        return $this->parameterType;
    }

    /**
     * @return SearchResultTypeName
     */
    public function getSearchResultTypeName(): SearchResultTypeName
    {
        return $this->searchResultTypeName;
    }

    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

}
