<?php

declare(strict_types=1);

namespace Sandstorm\KISSearch\Api\Query\Model;

readonly class SearchResult implements \JsonSerializable
{

    private SearchResultTypeName $resultTypeName;
    private ?array $groupMetaData;
    private ?array $metaData;

    public function __construct(
        private string $identifier,
        string $resultTypeName,
        private string $title,
        private ?string $url,
        private float $score,
        private int $matchCount,
        ?string $groupMetaData,
        ?string $metaData
    )
    {
        $this->resultTypeName = SearchResultTypeName::fromString($resultTypeName);
        $this->groupMetaData = $groupMetaData !== null ? json_decode($groupMetaData, true) : [];
        $this->metaData = $metaData !== null ? json_decode($metaData, true) : [];
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getResultTypeName(): SearchResultTypeName
    {
        return $this->resultTypeName;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getMatchCount(): int
    {
        return $this->matchCount;
    }

    public function getGroupMetaData(): array
    {
        return $this->groupMetaData;
    }

    public function getMetaData(): array
    {
        return $this->metaData;
    }

    public function jsonSerialize(): array
    {
        return [
            'identifier' => $this->identifier,
            'type' => $this->resultTypeName->getName(),
            'title' => $this->title,
            'url' => $this->url,
            'score' => $this->score,
            'matchCount' => $this->matchCount,
            'groupMetaData' => $this->groupMetaData,
            'metaData' => $this->metaData
        ];
    }
}