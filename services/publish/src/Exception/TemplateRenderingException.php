<?php

namespace App\Exception;

use RuntimeException;

final class TemplateRenderingException extends RuntimeException
{
    public static function noTemplateAvailable(object $object): TemplateRenderingException
    {
        return new TemplateRenderingException(
            sprintf('No template available for rendering message of type: %s.', $object::class)
        );
    }
}
