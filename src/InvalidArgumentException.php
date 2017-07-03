<?php

namespace Superbalist\Laravel4PSR6CacheBridge;

use Psr\Cache\InvalidArgumentException as InvalidArgumentExceptionInterface;

class InvalidArgumentException extends \InvalidArgumentException implements InvalidArgumentExceptionInterface
{
}
