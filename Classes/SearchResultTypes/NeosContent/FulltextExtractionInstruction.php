<?php

namespace Sandstorm\KISSearch\SearchResultTypes\NeosContent;

use Neos\Flow\Annotations\Proxy;
use RuntimeException;
use Sandstorm\KISSearch\SearchResultTypes\SearchBucket;

#[Proxy(false)]
class FulltextExtractionInstruction
{
    private readonly array $targetBuckets;
    private readonly FulltextExtractionMode $mode;

    /**
     * @param array $targetBuckets
     * @param FulltextExtractionMode $mode
     */
    private function __construct(array $targetBuckets, FulltextExtractionMode $mode)
    {
        $this->targetBuckets = $targetBuckets;
        $this->mode = $mode;
        if ($mode == FulltextExtractionMode::EXTRACT_INTO_SINGLE_BUCKET && count($targetBuckets) !== 1) {
            throw new RuntimeException(
                'Invalid fulltext extraction instruction; mode EXTRACT_INTO_SINGLE_BUCKET needs exactly one bucket, but was ' . count($targetBuckets),
                1689765454
            );
        }
    }

    /**
     * All text content is extracted into a single bucket.
     *
     * @param SearchBucket $targetBucket
     * @return FulltextExtractionInstruction
     */
    public static function extractIntoSingleBucket(SearchBucket $targetBucket): FulltextExtractionInstruction
    {
        return new FulltextExtractionInstruction([$targetBucket], FulltextExtractionMode::EXTRACT_INTO_SINGLE_BUCKET);
    }

    /**
     * The text content is extracted from HTML headline tags into their respective buckets.
     * h1, h2 -> critical
     * h3, h4, h5, h6 -> major
     * everything else -> normal
     *
     * @return FulltextExtractionInstruction
     */
    public static function extractHtmlTagsIntoAllBucketsNoMinor(): FulltextExtractionInstruction
    {
        return new FulltextExtractionInstruction([SearchBucket::CRITICAL, SearchBucket::MAJOR, SearchBucket::NORMAL], FulltextExtractionMode::EXTRACT_HTML_TAGS);
    }

    /**
     * Works similar to extractHtmlTagsIntoAllBuckets() but with bucket specific filtering.
     *
     * Example:
     *  - given the text content "foo <h1>bar</h1> something else <h3>baz</h3>"
     *  - extractHtmlTagsIntoSpecificBuckets(MAJOR, NORMAL) results in:
     *     - MAJOR: "baz"
     *     - NORMAL: "foo something else"
     *     - note, that in this case, the h1 / CRITICAL bucket is filtered, as it is not passed as parameter
     *
     * @param SearchBucket ...$targetBuckets
     * @return FulltextExtractionInstruction
     */
    public static function extractHtmlTagsIntoSpecificBuckets(SearchBucket ...$targetBuckets): FulltextExtractionInstruction
    {
        return new FulltextExtractionInstruction($targetBuckets, FulltextExtractionMode::EXTRACT_HTML_TAGS);
    }

    /**
     * @return array
     */
    public function getTargetBuckets(): array
    {
        return $this->targetBuckets;
    }

    /**
     * @return FulltextExtractionMode
     */
    public function getMode(): FulltextExtractionMode
    {
        return $this->mode;
    }

}
