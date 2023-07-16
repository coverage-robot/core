<?php

namespace App\Query\Result;

use App\Exception\QueryException;
use Packages\Models\Model\Tag;

class TagCoverageQueryResult extends CoverageQueryResult
{
    private function __construct(
        private readonly Tag $tag,
        readonly float $coveragePercentage,
        readonly int $lines,
        readonly int $covered,
        readonly int $partial,
        readonly int $uncovered
    ) {
        parent::__construct(
            $coveragePercentage,
            $lines,
            $covered,
            $partial,
            $uncovered
        );
    }

    /**
     * @throws QueryException
     */
    public static function from(array $result): self
    {
        if (
            is_string($result['tag'] ?? null) &&
            is_string($result['commit'] ?? null)
        ) {
            $coverage = parent::from($result);

            return new self(
                Tag::from($result),
                $coverage->getCoveragePercentage(),
                $coverage->getLines(),
                $coverage->getCovered(),
                $coverage->getPartial(),
                $coverage->getUncovered()
            );
        }

        throw QueryException::invalidQueryResult();
    }

    public function getTag(): Tag
    {
        return $this->tag;
    }
}
