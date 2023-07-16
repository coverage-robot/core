<?php

namespace App\Query\Result;

use App\Exception\QueryException;
use Google\Cloud\Core\Iterator\ItemIterator;
use Packages\Models\Model\Tag;

class CommitQueryResult implements QueryResultInterface
{
    /**
     * @param string $commit
     * @param Tag[] $tags
     */
    private function __construct(
        private readonly string $commit,
        private readonly array $tags
    ) {
    }

    /**
     * @throws QueryException
     */
    public static function from(ItemIterator|array $result): self
    {
        if (
            is_string($result['commit'] ?? null) &&
            is_array($result['tags'] ?? null)
        ) {
            return new self(
                (string)$result['commit'],
                array_map(
                    fn (string $name) => new Tag($name, (string)$result['commit']),
                    $result['tags']
                )
            );
        }

        throw QueryException::invalidQueryResult();
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    public function getTags(): array
    {
        return $this->tags;
    }
}
