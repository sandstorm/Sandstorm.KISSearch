<?php

namespace Sandstorm\KISSearch\FusionObjects;

use Neos\Flow\Annotations\Proxy;
use RuntimeException;

#[Proxy(false)]
class InvalidFusionValueException extends RuntimeException
{
    public function __construct(string $message, int $code)
    {
        parent::__construct($message, $code);
    }
}
