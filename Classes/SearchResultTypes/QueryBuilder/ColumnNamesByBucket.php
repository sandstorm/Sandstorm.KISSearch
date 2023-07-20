<?php

namespace Sandstorm\KISSearch\SearchResultTypes\QueryBuilder;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
class ColumnNamesByBucket
{

    private readonly ?array $critical;
    private readonly ?array $major;
    private readonly ?array $normal;
    private readonly ?array $minor;

    /**
     * @param array|null $critical
     * @param array|null $major
     * @param array|null $normal
     * @param array|null $minor
     */
    public function __construct(?array $critical, ?array $major, ?array $normal, ?array $minor)
    {
        $this->critical = $critical;
        $this->major = $major;
        $this->normal = $normal;
        $this->minor = $minor;
    }

    /**
     * @return array|null
     */
    public function getCritical(): ?array
    {
        return $this->critical;
    }

    /**
     * @return array|null
     */
    public function getMajor(): ?array
    {
        return $this->major;
    }

    /**
     * @return array|null
     */
    public function getNormal(): ?array
    {
        return $this->normal;
    }

    /**
     * @return array|null
     */
    public function getMinor(): ?array
    {
        return $this->minor;
    }

    public function getAllColumnNames(): array
    {
        return array_merge(
            $this->critical ?: [],
            $this->major ?: [],
            $this->normal ?: [],
            $this->minor ?: []
        );
    }

}
