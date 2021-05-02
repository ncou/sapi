<?php

declare(strict_types=1);

namespace Chiron\Sapi\Exception;

use RuntimeException;
use Throwable;

class EmitterException extends RuntimeException
{
    public static function forHeadersSent() : self
    {
        return new self('Unable to emit response; headers already sent');
    }

    public static function forOutputSent() : self
    {
        return new self('Output has been emitted previously; cannot emit response');
    }
}
