<?php

namespace App\Exception;

use RuntimeException;

class PublishException extends RuntimeException
{
    public static function notSupportedException(): PublishException
    {
        return new PublishException('Publisher is not supported for upload.');
    }
}
