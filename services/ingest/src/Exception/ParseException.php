<?php

namespace App\Exception;

use RuntimeException;

final class ParseException extends RuntimeException
{
    public static function notSupportedException(): ParseException
    {
        return new ParseException(
            'An error occurred during parsing: The content passed is not supported by this parser.'
        );
    }
}
