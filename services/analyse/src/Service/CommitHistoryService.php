<?php

namespace App\Service;

use App\Service\History\CommitHistoryServiceInterface;
use Packages\Models\Model\Upload;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class CommitHistoryService
{
    /**
     * @param array<array-key, CommitHistoryServiceInterface> $parsers
     */
    public function __construct(
        #[TaggedIterator('app.commit_history', defaultIndexMethod: 'getProvider')]
        private readonly iterable $parsers
    ) {
    }

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
