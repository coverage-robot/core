<?php

namespace App\Model\Webhook;

use DateTimeImmutable;

interface PushedCommitInterface
{
    public function getCommit(): string;

    /**
     * @return string[]
     */
    public function getAddedFiles(): array;

    /**
     * @return string[]
     */
    public function getModifiedFiles(): array;

    /**
     * @return string[]
     */
    public function getDeletedFiles(): array;

    public function getCommittedAt(): DateTimeImmutable;
}
