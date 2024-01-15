<?php

namespace App\Model\Webhook;

use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

interface PushedCommitInterface
{
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[a-f0-9]{40}$/')]
    public function getCommit(): string;

    /**
     * @return string[]
     */
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Type('string'),
    ])]
    public function getAddedFiles(): array;

    /**
     * @return string[]
     */
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Type('string'),
    ])]
    public function getModifiedFiles(): array;

    /**
     * @return string[]
     */
    #[Assert\All([
        new Assert\NotBlank(),
        new Assert\Type('string'),
    ])]
    public function getDeletedFiles(): array;

    #[Assert\LessThanOrEqual('now')]
    public function getCommittedAt(): DateTimeImmutable;
}
