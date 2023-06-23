<?php

namespace App\Service\Diff;

use Packages\Models\Model\Upload;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.diff_parser')]
interface DiffParserServiceInterface
{
    /**
     * Get diffs added lines for a given upload.
     *
     * The returned added lines are grouped by file, and
     * will either be from the commit, or the PR, depending
     * on the context of the upload.
     *
     * @param Upload $upload
     * @return array
     */
    public function get(Upload $upload): array;

    /**
     * Get the provider that the diff parser supports.
     */
    public static function getProvider(): string;
}
