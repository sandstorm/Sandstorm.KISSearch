<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\Flow\Annotations\Proxy;
use Sandstorm\KISSearch\SearchResultTypes\QueryBuilder\AdditionalJsonParameterValueInterface;

#[Proxy(false)]
class ContentDimensionValuesFilter implements AdditionalJsonParameterValueInterface
{

    private readonly array $input;

    /**
     * @param array $input
     */
    public function __construct(array $input)
    {
        $this->input = $input;
    }

    /**
     * @return array
     */
    public function getInput(): array
    {
        return $this->input;
    }

    public function toParameterValue(): array
    {
        $result = [];
        foreach ($this->input as $dimensionName => $dimensionValues) {
            foreach ($dimensionValues as $idx => $dimensionValue) {
                $result[] = [
                    'dimension_name' => $dimensionName,
                    'index_key' => $idx,
                    'filter_value' => $dimensionValue
                ];
            }
        }
        return $result;
    }

}
