<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class ResultSearchingQueryParts extends \ArrayObject
{

    /**
     * @param ResultSearchingQueryPartInterface[] $values
     */
    private function __construct(array $values)
    {
        parent::__construct($values);
    }

    public static function create(ResultSearchingQueryPartInterface ...$values): ResultSearchingQueryParts
    {
        return new ResultSearchingQueryParts($values);
    }

    public static function singlePart(ResultSearchingQueryPartInterface $value): ResultSearchingQueryParts
    {
        return self::create($value);
    }

    /**
     * @param ResultSearchingQueryParts[] $arrayOfParts
     * @return ResultSearchingQueryParts
     */
    public static function merging(array $arrayOfParts): ResultSearchingQueryParts
    {
        $values = [];
        foreach ($arrayOfParts as $parts) {
            foreach ($parts as $part) {
                $values[] = $part;
            }
        }
        return new ResultSearchingQueryParts($values);
    }

}
