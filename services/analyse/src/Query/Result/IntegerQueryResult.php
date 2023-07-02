<?php

namespace App\Query\Result;

class IntegerQueryResult implements QueryResultInterface
{
    private function __construct(private readonly int $result)
    {
    }

    public static function from(int $result): self
    {
        return new self($result);
    }

    public function getResult(): int
    {
        return $this->result;
    }
}
