<?php

namespace Modules\Courier\app\Exceptions;

class UnsupportedCourierOperationException extends CourierException
{
    public static function for(string $provider, string $operation): self
    {
        return new self("Provider [{$provider}] does not support operation [{$operation}].");
    }
}
