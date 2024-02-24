<?php

namespace Packages\Contracts\Tag;

use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

class Tag implements Stringable
{
    /**
     * @param int[] $successfullyUploadedLines
     */
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-zA-Z0-9\.\-_]+$/')]
        private readonly string $name,
        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^[a-f0-9]{40}$/')]
        private readonly string $commit,
        #[Assert\All([
            new Assert\Type(type: 'int'),
            new Assert\Positive()
        ])]
        private readonly array $successfullyUploadedLines,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCommit(): string
    {
        return $this->commit;
    }

    /**
     * @return int[]
     */
    public function getSuccessfullyUploadedLines(): array
    {
        return $this->successfullyUploadedLines;
    }

    public function __toString(): string
    {
        return sprintf("Tag#%s-%s", $this->name, $this->commit);
    }
}
