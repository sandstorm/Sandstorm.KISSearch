<?php

namespace Sandstorm\KISSearch\Neos\Schema\Model;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
enum FulltextExtractionMode
{
    /**
     * Extract the text into one specific bucket.
     */
    case EXTRACT_INTO_SINGLE_BUCKET;

    /**
     * Extract the text into multiple buckets, based on the HTML headline tags.
     *
     * h1, h2 -> critical
     * h3, h4, h5, h6 -> major
     * everything else -> normal
     */
    case EXTRACT_HTML_TAGS;

}
