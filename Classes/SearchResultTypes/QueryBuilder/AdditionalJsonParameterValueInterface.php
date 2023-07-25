<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

interface AdditionalJsonParameterValueInterface
{

    public function toParameterValue(): array;

}
