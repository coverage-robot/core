<?php

namespace App\Service\Diff;

use Packages\Models\Model\Event\Upload;
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
     * @return array<array-key, int[]>
     */
    public function get(Upload $upload): array;
}
