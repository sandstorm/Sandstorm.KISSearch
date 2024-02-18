<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\InvalidAdditionalParameterException;
use Sandstorm\KISSearch\SearchResultTypes\SearchResultTypeName;
use Closure;

#[Proxy(false)]
class AdditionalQueryParameterDefinition
{

    public const TYPE_STRING = 'string';
    public const TYPE_STRING_ARRAY = 'string_array';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_FLOAT = 'float';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_JSON = 'json';

    private readonly string $parameterName;
    private readonly string $parameterType;
    private readonly SearchResultTypeName $searchResultTypeName;
    private readonly bool $required;
    private readonly ?Closure $valueConverter;

    /**
     * @param string $parameterName
     * @param string $parameterType
     * @param SearchResultTypeName $searchResultTypeName
     * @param bool $required
     * @param Closure|null $valueConverter
     */
    private function __construct(string $parameterName, string $parameterType, SearchResultTypeName $searchResultTypeName, bool $required, ?Closure $valueConverter)
    {
        if (!preg_match('/\w+/', $parameterName)) {
            throw new InvalidAdditionalParameterException(
                "Additional parameter name must contain only word characters; but was '$parameterName'",
                1689982704
            );
        }
        if (!in_array($parameterType, [self::TYPE_STRING, self::TYPE_STRING_ARRAY, self::TYPE_INTEGER, self::TYPE_FLOAT, self::TYPE_BOOLEAN, self::TYPE_JSON])) {
            throw new InvalidAdditionalParameterException(
                sprintf(
                    "Type of additional parameter '%s' must be one of '%s', '%s', '%s', '%s', '%s' or '%s'; but was '%s'",
                    $parameterName,
                    self::TYPE_STRING, self::TYPE_STRING_ARRAY, self::TYPE_INTEGER, self::TYPE_FLOAT, self::TYPE_BOOLEAN, self::TYPE_JSON,
                    $parameterType
                ),
                1689984588
            );
        }
        $this->parameterName = $parameterName;
        $this->parameterType = $parameterType;
        $this->searchResultTypeName = $searchResultTypeName;
        $this->required = $required;
        $this->valueConverter = $valueConverter;
    }

    public static function optional(string $parameterName, string $parameterType, SearchResultTypeName $searchResultTypeName, ?Closure $valueConverter = null): AdditionalQueryParameterDefinition
    {
        return new AdditionalQueryParameterDefinition($parameterName, $parameterType, $searchResultTypeName, false, $valueConverter);
    }

    public static function required(string $parameterName, string $parameterType, SearchResultTypeName $searchResultTypeName, ?Closure $valueConverter = null): AdditionalQueryParameterDefinition
    {
        return new AdditionalQueryParameterDefinition($parameterName, $parameterType, $searchResultTypeName, true, $valueConverter);
    }

    public static function optionalJson(string $parameterName, SearchResultTypeName $searchResultTypeName, ?Closure $valueConverter = null): AdditionalQueryParameterDefinition
    {
        return new AdditionalQueryParameterDefinition($parameterName, self::TYPE_JSON, $searchResultTypeName, false, $valueConverter);
    }

    public static function requiredJson(string $parameterName, SearchResultTypeName $searchResultTypeName, ?Closure $valueConverter = null): AdditionalQueryParameterDefinition
    {
        return new AdditionalQueryParameterDefinition($parameterName, self::TYPE_JSON, $searchResultTypeName, true, $valueConverter);
    }

    public function withParameterValue(mixed $parameterValue): AdditionalQueryParameterValue
    {
        $converterFunction = $this->valueConverter;
        if ($converterFunction !== null && $parameterValue !== null) {
            $parameterValue = $converterFunction($parameterValue);
        }
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
