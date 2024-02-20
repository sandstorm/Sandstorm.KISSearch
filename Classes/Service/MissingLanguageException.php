<?php

namespace Sandstorm\KISSearch\Service;

use Neos\Flow\Annotations\Proxy;
use RuntimeException;

#[Proxy(false)]
class MissingLanguageException extends RuntimeException
{
    public function __construct(string $message, int $code)
    {
        parent::__construct($message, $code);
    }
}
