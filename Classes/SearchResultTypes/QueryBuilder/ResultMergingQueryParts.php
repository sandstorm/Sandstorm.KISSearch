<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use ArrayObject;
use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class ResultMergingQueryParts extends ArrayObject
{

    /**
     * @param ResultMergingQueryPartInterface[] $values
     */
    private function __construct(array $values)
    {
        parent::__construct($values);
    }

    public static function create(ResultMergingQueryPartInterface ...$values): ResultMergingQueryParts
    {
        return new ResultMergingQueryParts($values);
    }

    public static function singlePart(ResultMergingQueryPartInterface $value): ResultMergingQueryParts
    {
        return self::create($value);
    }

    /**
     * @param ResultMergingQueryParts[] $arrayOfParts
     * @return ResultMergingQueryParts
     */
    public static function merging(array $arrayOfParts): ResultMergingQueryParts
    {
        $values = [];
        foreach ($arrayOfParts as $parts) {
            foreach ($parts as $part) {
                $values[] = $part;
            }
        }
        return new ResultMergingQueryParts($values);
    }

}
