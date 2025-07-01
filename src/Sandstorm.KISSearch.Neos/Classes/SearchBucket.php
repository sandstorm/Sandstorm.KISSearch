<?php

namespace Sandstorm\KISSearch\Neos;

use Neos\Flow\Annotations\Proxy;

#[Proxy(false)]
enum SearchBucket: string
{
    case CRITICAL = 'critical';
    case MAJOR = 'major';
    case NORMAL = 'normal';
    case MINOR = 'minor';

    public static function allBuckets(): array
    {
        return [SearchBucket::CRITICAL, SearchBucket::MAJOR, SearchBucket::NORMAL, SearchBucket::MINOR];
    }

}
