<?php

namespace Sandstorm\KISSearch;

use Neos\Flow\Annotations\Proxy;
use RuntimeException;

#[Proxy(false)]
class InvalidConfigurationException extends RuntimeException
{
    public function __construct(string $message, int $code)
    {
        parent::__construct($message, $code);
    }
}
