<?php

namespace Sandstorm\KISSearch\FusionObjects;

use Neos\Fusion\FusionObjects\AbstractFusionObject;
use Sandstorm\KISSearch\Service\SearchQueryInput;

abstract class AbstractSearchImplementation extends AbstractFusionObject
{

    protected abstract function doSearchQuery(SearchQueryInput $searchQuery, int $limit): array;

    /**
     * @return ?string
     */
    private function getQuery(): ?string
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
    private function getLimit(): int
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

    private function getAdditionalParameters(): ?array
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

    private function getLanguage(): ?string
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

    /**
     * @return array
     */
    public function evaluate(): array
    {
        $query = $this->getQuery();
        if ($query === null) {
            // no query, no results! it's that simple ;)
            return [];
        }
        $searchQuery = new SearchQueryInput(
            $query,
            $this->getAdditionalParameters(),
            $this->getLanguage()
        );
        return $this->doSearchQuery($searchQuery, $this->getLimit());
    }
}
