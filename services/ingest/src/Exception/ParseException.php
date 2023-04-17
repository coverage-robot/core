<?php

namespace App\Exception;

class ParseException extends \RuntimeException
{
    public static function lineTypeParseException(?string $type): ParseException
    {
        return new ParseException(
            sprintf('Unable to parse line type: %s', $type)
        );
    }

    public static function notSupportedException(): ParseException
    {
        return new ParseException('The content passed is not supported by this parser.');
    }
}
