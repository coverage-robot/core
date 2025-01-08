<?php

declare(strict_types=1);

namespace App\Exception;

use App\Query\Result\QueryResultInterface;
use Exception;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class QueryException extends Exception
{
    public static function invalidResult(
        QueryResultInterface $result,
        ConstraintViolationListInterface $errors
    ): self {
        return new self(
            sprintf(
                'Invalid query result with type %s. Violations: %s',
                get_class($result),
                (string)$errors
            )
        );
    }
}
