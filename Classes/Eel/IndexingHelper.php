<?php

namespace Sandstorm\KISSearch\Eel;

use Neos\Eel\ProtectedContextAwareInterface;
use Sandstorm\KISSearch\SearchResultTypes\NeosContent\FulltextExtractionInstruction;
use Sandstorm\KISSearch\SearchResultTypes\SearchBucket;

class IndexingHelper implements ProtectedContextAwareInterface
{

    public static function extractInto(string $bucketName, ?string $value): FulltextExtractionInstruction
    {
        $targetBucket = match(trim($bucketName)) {
            'h1', 'h2' => SearchBucket::CRITICAL,
            'h3', 'h4', 'h5', 'h6' => SearchBucket::MAJOR,
            'text' => SearchBucket::NORMAL,
            default => SearchBucket::MINOR
        };
        return FulltextExtractionInstruction::extractIntoSingleBucket($targetBucket);
    }

    public static function extractHtmlTags(?string $value): FulltextExtractionInstruction
    {
        return FulltextExtractionInstruction::extractHtmlTagsIntoAllBuckets();
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
