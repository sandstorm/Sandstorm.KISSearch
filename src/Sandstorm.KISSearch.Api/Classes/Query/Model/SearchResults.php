<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Model;

use Traversable;

readonly class SearchResults implements \IteratorAggregate, \ArrayAccess, \Countable
{

    /**
     * @param float $queryExecutionTimeInMs
     * @param array<SearchResult> $results
     */
    public function __construct(
        private float $queryExecutionTimeInMs,
        private array $results
    ) {
    }

    public function getQueryExecutionTimeInMs(): float
    {
        return $this->queryExecutionTimeInMs;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->results);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->results[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('Search results are immutable');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('Search results are immutable');
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->results);
    }

    public function count(): int
    {
        return count($this->results);
    }
}