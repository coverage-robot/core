<?php

namespace App\Service\Diff;

use Packages\Event\Model\EventInterface;
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
     * @param EventInterface $event
     * @return array<array-key, int[]>
     */
    public function get(EventInterface $event): array;
}
