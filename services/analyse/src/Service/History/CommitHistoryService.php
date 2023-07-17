<?php

namespace App\Service\History;

use Packages\Models\Model\Upload;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CommitHistoryService implements CommitHistoryServiceInterface
{
    /**
     * @param array<array-key, CommitHistoryServiceInterface> $parsers
     */
    public function __construct(
        #[TaggedIterator(
            'app.commit_history',
            exclude: ['CommitHistoryService'],
            defaultIndexMethod: 'getProvider'
        )]
        private readonly iterable $parsers
    ) {
    }

    /**
     * Get the commits which preceded the given upload - these are the parent commits
     * of the one recorded during the upload.
     *
     * @throws RuntimeException
     */
    public function getPrecedingCommits(Upload $upload): array
    {
        $service = (iterator_to_array($this->parsers)[$upload->getProvider()->value]) ?? null;

        if (!$service instanceof CommitHistoryServiceInterface) {
            throw new RuntimeException(
                sprintf(
                    'No commit history service for %s',
                    $upload->getProvider()->value
                )
            );
        }

        return $service->getPrecedingCommits($upload);
    }
}
