<?php

namespace Superbalist\Laravel4PSR6CacheBridge;

use Exception;
use Psr\Cache\InvalidArgumentException as InvalidArgumentExceptionInterface;

class InvalidArgumentException extends Exception implements InvalidArgumentExceptionInterface
{
}
