<?php

namespace Sandstorm\KISSearch\FusionObjects;

use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Sandstorm\KISSearch\SearchResultTypes\NeosContent\NeosContentSearchResultType;

abstract class AbstractSearchImplementation extends AbstractFusionObject
{

    /**
     * @return ?string
     */
    protected function getQuery(): ?string
    {
        $queryFusionValue = $this->fusionValue('query');
        if ($queryFusionValue === null) {
            return null;
        }
        if (!is_string($queryFusionValue)) {
            throw new InvalidFusionValueException(
                "Fusion path 'query' must evaluate to a string, but was: $queryFusionValue",
                1689951637
            );
        }
        return $queryFusionValue;
    }

    /**
     * @return int
     */
    protected function getLimit(): int
    {
        $limitFusionValue = $this->fusionValue('limit');
        if ($limitFusionValue === null) {
            // default value as fallback
            return 50;
        }
        if (!is_int($limitFusionValue)) {
            throw new InvalidFusionValueException(
                "Fusion path 'limit' must evaluate to an integer, but was: $limitFusionValue",
                1689951801
            );
        }
        return $limitFusionValue;
    }

    /**
     * @return array
     */
    protected function getLimitPerResultType(): array
    {
        $limitFusionValue = $this->fusionValue('limit');
        if ($limitFusionValue === null) {
            // default value as fallback
            return [
                NeosContentSearchResultType::TYPE_NAME => 50
            ];
        }
        if (!is_array($limitFusionValue)) {
            throw new InvalidFusionValueException(
                "Fusion path 'limit' must evaluate to an array, but was: $limitFusionValue",
                1689951804
            );
        }
        return $limitFusionValue;
    }

    protected function getAdditionalParameters(): ?array
    {
        $additionalParametersFusionValue = $this->fusionValue('additionalParameters');
        if ($additionalParametersFusionValue === null) {
            return null;
        }
        if (!is_array($additionalParametersFusionValue)) {
            throw new InvalidFusionValueException(
                "Fusion path 'additionalParameters' must evaluate to an array, but was: $additionalParametersFusionValue",
                1689988600
            );
        }
        return $additionalParametersFusionValue;
    }

    protected function getLanguage(): ?string
    {
        $languageFusionValue = $this->fusionValue('language');
        if ($languageFusionValue === null) {
            return null;
        }
        if (!is_string($languageFusionValue)) {
            throw new InvalidFusionValueException(
                "Fusion path 'language' must evaluate to a string, but was: $languageFusionValue",
                1689988600
            );
        }
        return $languageFusionValue;
    }

}
