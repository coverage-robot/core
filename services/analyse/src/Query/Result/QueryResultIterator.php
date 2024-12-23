<?php

namespace App\Query\Result;

use Closure;
use Countable;
use Iterator;

/**
 * @template T of QueryResultInterface
 *
 * @template-implements Iterator<int, T>
 */
final class QueryResultIterator implements QueryResultInterface, Iterator, Countable
{
    /**
     * @param Closure(mixed $row): ?T $parser
     */
    public function __construct(
        private readonly Iterator $rows,
        private readonly int $count,
        private readonly Closure $parser
    ) {
    }

    /**
     * @return T|null
     */
    public function current(): ?QueryResultInterface
    {
        /** @var mixed $current */
        $current = $this->rows->current();

        if ($current === null) {
            return null;
        }

        return ($this->parser)($current);
    }

    public function key(): mixed
    {
        return $this->rows->key();
    }

    public function valid(): bool
    {
        return $this->rows->valid();
    }

    public function rewind(): void
    {
        $this->rows->rewind();
    }

    public function next(): void
    {
        $this->rows->next();
    }

    public function count(): int
    {
        return $this->count;
    }

    public function getTimeToLive(): int|false
    {
        if ($this->count === 0) {
            return false;
        }

        return $this->current()?->getTimeToLive() ?? false;
    }
}
