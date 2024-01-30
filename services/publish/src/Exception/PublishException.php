<?php

namespace App\Exception;

use RuntimeException;

final class PublishException extends RuntimeException
{
    public static function notSupportedException(): PublishException
    {
        return new PublishException('Publisher is not supported for upload.');
    }

    public static function notFoundException(string $entity): PublishException
    {
        return new PublishException(sprintf('Publisher did not find %s', $entity));
    }
}
