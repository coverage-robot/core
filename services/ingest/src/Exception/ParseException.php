<?php

namespace App\Exception;

use RuntimeException;

class ParseException extends RuntimeException
{
    public static function lineTypeParseException(string $type): ParseException
    {
        return new ParseException(
            sprintf(
                'An error occurred during parsing: "%s" is not a valid line type.',
                $type
            )
        );
    }

    public static function notSupportedException(): ParseException
    {
        return new ParseException(
            'An error occurred during parsing: The content passed is not supported by this parser.'
        );
    }
}
