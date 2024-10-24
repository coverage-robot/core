<?php

declare(strict_types=1);

namespace App\Query\Result;

use Symfony\Component\Validator\Constraints as Assert;

final class LineCoverageCollectionQueryResult implements QueryResultInterface
{
    /**
     * @param LineCoverageQueryResult[] $lines
     */
    public function __construct(
        #[Assert\All([
            new Assert\Type(type: LineCoverageQueryResult::class)
        ])]
        private readonly array $lines,
    ) {
    }

    /**
     * @return LineCoverageQueryResult[]
     */
    public function getLines(): array
    {
        return $this->lines;
    }
}
