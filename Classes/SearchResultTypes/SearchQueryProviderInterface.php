<?php

namespace Sandstorm\KISSearch\SearchResultTypes;

use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\AdditionalQueryParameterDefinition;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\AdditionalQueryParameterDefinitions;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\ResultMergingQueryParts;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\ResultSearchingQueryParts;

interface SearchQueryProviderInterface
{

    function getResultSearchingQueryParts(): ResultSearchingQueryParts;

    function getResultMergingQueryParts(): ResultMergingQueryParts;

    function getAdditionalQueryParameters(): ?AdditionalQueryParameterDefinitions;

}
