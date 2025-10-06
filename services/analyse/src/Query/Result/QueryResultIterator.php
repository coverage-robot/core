<?php

declare(strict_types=1);

namespace App\Query\Result;

use Closure;
use Countable;
use Iterator;
use Override;

/**
 * @template Item of QueryResultInterface
 *
 * @template-implements Iterator<int, Item>
 */
final readonly class QueryResultIterator implements QueryResultInterface, Iterator, Countable
{
    /**
     * @param Closure(mixed $row): Item $parser
     */
    public function __construct(
        private Iterator $rows,
        private int $count,
        private Closure $parser
    ) {
    }

    /**
     * @return Item
     */
    #[Override]
    public function current(): QueryResultInterface
    {
        /** @var mixed $current */
        $current = $this->rows->current();

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

        return $this->current()->getTimeToLive();
    }
}
