<?php

namespace App\Service;

use App\Service\Diff\DiffParserServiceInterface;
use Packages\Models\Model\Upload;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use WeakMap;

class DiffParserService
{
    private WeakMap $cache;

    /**
     * @param array<array-key, DiffParserServiceInterface> $parsers
     */
    public function __construct(
        #[TaggedIterator('app.diff_parser', defaultIndexMethod: 'getProvider')]
        private readonly iterable $parsers,
        private readonly LoggerInterface $diffParserLogger
    ) {
        $this->cache = new WeakMap();
    }

    public function get(Upload $upload): array
    {
        if (isset($this->cache[$upload])) {
            $this->diffParserLogger->info(
                sprintf(
                    'Using cached diff for %s, which has %s files with added lines.',
                    (string)$upload,
                    count($this->cache[$upload])
                ),
                [
                    'owner' => $upload->getOwner(),
                    'repository' => $upload->getRepository(),
                    'commit' => $upload->getCommit(),
                    'pullRequest' => $upload->getPullRequest(),
                    $this->cache[$upload]
                ]
            );

            return $this->cache[$upload];
        }

        $reader = (iterator_to_array($this->parsers)[$upload->getProvider()->value]) ?? null;

        if (!$reader instanceof DiffParserServiceInterface) {
            throw new RuntimeException(
                sprintf(
                    'No diff reader found for %s',
                    $upload->getProvider()->value
                )
            );
        }

        $this->cache[$upload] = $reader->get($upload);

        return $this->cache[$upload];
    }
}
