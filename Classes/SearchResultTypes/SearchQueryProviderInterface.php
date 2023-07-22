<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

interface SearchQueryProviderInterface
{

    function getResultSearchingQueryPart(): string;

    function getResultMergingQueryPart(): string;

    /**
     * @return AdditionalQueryParameterDefinition[]|null
     */
    function getAdditionalQueryParameters(): ?array;

}
