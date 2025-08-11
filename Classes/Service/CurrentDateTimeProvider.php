<?php

namespace Sandstorm\KISSearch\Service;

use DateTimeImmutable;
use Neos\Flow\Annotations as Flow;

/**
 * Intent to be overridden in e2e tests.
 * @Flow\Scope('singleton')
 */
class CurrentDateTimeProvider
{

    /**
     * @return DateTimeImmutable the now time
     */
    public function getCurrentDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

}
