<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use ArrayObject;
use Neos\Flow\Annotations\Proxy;

/**
 * @Proxy(false)
 */
class ResultMergingQueryParts
{

    private readonly array $values;
    private readonly ?string $groupMetadataSelector;
    private readonly ?string $groupBy;

    /**
     * @param ResultMergingQueryPartInterface[] $values
     */
    public function __construct(array $values, ?string $groupMetadataSelector, ?string $groupBy)
    {
        $this->values = $values;
        $this->groupMetadataSelector = $groupMetadataSelector;
        $this->groupBy = $groupBy;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getGroupMetadataSelector(): ?string
    {
        return $this->groupMetadataSelector;
    }

    public function getGroupBy(): ?string
    {
        return $this->groupBy;
    }

}
