<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use ArrayObject;
use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class ResultMergingQueryParts
{

    private readonly array $values;
    private readonly ?string $groupMetadataSelector;

    /**
     * @param ResultMergingQueryPartInterface[] $values
     */
    private function __construct(array $values, ?string $groupMetadataSelector)
    {
        $this->values = $values;
        $this->groupMetadataSelector = $groupMetadataSelector;
    }

    public static function create(ResultMergingQueryPartInterface ...$values): ResultMergingQueryParts
    {
        return new ResultMergingQueryParts($values, null);
    }

    public static function singlePart(ResultMergingQueryPartInterface $value): ResultMergingQueryParts
    {
        return self::create($value);
    }

    public static function createWithGroupMetadata(string $groupMetadataSelector, ResultMergingQueryPartInterface ...$values): ResultMergingQueryParts
    {
        return new ResultMergingQueryParts($values, $groupMetadataSelector);
    }

    public static function singlePartWithGroupMetadata(string $groupMetadataSelector, ResultMergingQueryPartInterface $value): ResultMergingQueryParts
    {
        return self::createWithGroupMetadata($groupMetadataSelector, $value);
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getGroupMetadataSelector(): ?string
    {
        return $this->groupMetadataSelector;
    }

}
