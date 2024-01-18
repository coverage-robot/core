<?php

namespace App\Model\Line;

use Override;
use Packages\Contracts\Line\LineType;
use Stringable;
use Symfony\Component\Serializer\Annotation\DiscriminatorMap;
use Symfony\Component\Serializer\Annotation\Ignore;

#[DiscriminatorMap(
    'type',
    [
        LineType::STATEMENT->value => Statement::class,
        LineType::BRANCH->value => Branch::class,
        LineType::METHOD->value => Method::class,
    ]
)]
abstract class AbstractLine implements Stringable
{
    public function __construct(
        private readonly int $lineNumber,
        private readonly int $lineHits = 0
    ) {
    }

    abstract public function getType(): LineType;

    /**
     * Build a unique line identifier, which can be used for indexing a lookups.
     *
     * Generally this will be the line number, however there are specific times we may
     * need to uniquely identify a line slightly differently.
     *
     * For example, when tracking coverage for a method, the line number does not dictate
     * that a second method may not also reside on this line. In this particular case,
     * we need to use the method name.
     *
     *
     * @see Method::getUniqueLineIdentifier()
     */
    #[Ignore]
    public function getUniqueLineIdentifier(): string
    {
        return (string)$this->getLineNumber();
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getLineHits(): int
    {
        return $this->lineHits;
    }

    #[Override]
    public function __toString(): string
    {
        return sprintf(
            '%s#%s',
            ucfirst(strtolower($this->getType()->value)),
            $this->getUniqueLineIdentifier()
        );
    }
}
