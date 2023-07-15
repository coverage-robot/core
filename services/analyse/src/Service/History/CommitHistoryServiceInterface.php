<?php

namespace App\Service\History;

use Packages\Models\Model\Upload;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.commit_history')]
interface CommitHistoryServiceInterface
{
    /**
     * @return string[]
     */
    public function getPrecedingCommits(Upload $upload): array;

    /**
     * Get the provider that the diff parser supports.
     */
    public static function getProvider(): string;
}
