<?php

namespace App\Query\Result;

use App\Enum\QueryResult;
use Packages\Models\Model\Tag;

class TagCoverageQueryResult extends CoverageQueryResult
{
    public function __construct(
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

    public function getTag(): Tag
    {
        return $this->tag;
    }

    /**
     * Since this implements the coverage query result base class, the type needs to
     * be overridden so that the serializer knows the correct discriminator value to use
     * when serializing/de-serializing this object.
     *
     * @see QueryResultInterface
     */
    public function getType(): string
    {
        return QueryResult::TAG_COVERAGE->value;
    }
}
