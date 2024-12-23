<?php

declare(strict_types=1);

namespace App\Query\Result;

use Closure;
use Countable;
use Iterator;
use Override;

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
    #[Override]
    public function current(): ?QueryResultInterface
    {
        /** @var mixed $current */
        $current = $this->rows->current();

        if ($current === null) {
            return null;
        }

        return ($this->parser)($current);
    }

    #[Override]
    public function key(): mixed
    {
        return $this->rows->key();
    }

    #[Override]
    public function valid(): bool
    {
        return $this->rows->valid();
    }

    #[Override]
    public function rewind(): void
    {
        $this->rows->rewind();
    }

    #[Override]
    public function next(): void
    {
        $this->rows->next();
    }

    #[Override]
    public function count(): int
    {
        return $this->count;
    }

    #[Override]
    public function getTimeToLive(): int|false
    {
        if ($this->count === 0) {
            return false;
        }

        return $this->current()?->getTimeToLive() ?? false;
    }
}
