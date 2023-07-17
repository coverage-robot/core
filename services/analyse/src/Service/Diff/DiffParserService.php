<?php

namespace App\Service\Diff;

use App\Service\ProviderAwareInterface;
use Packages\Models\Model\Upload;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class DiffParserService implements DiffParserServiceInterface
{
    /**
     * @param array<array-key, DiffParserServiceInterface&ProviderAwareInterface> $parsers
     */
    public function __construct(
        #[TaggedIterator(
            'app.diff_parser',
            exclude: ['CachingDiffParserService', 'DiffParserService'],
            defaultIndexMethod: 'getProvider'
        )]
        private readonly iterable $parsers
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(Upload $upload): array
    {
        $reader = (iterator_to_array($this->parsers)[$upload->getProvider()->value]) ?? null;

        if (!$reader instanceof DiffParserServiceInterface) {
            throw new RuntimeException(
                sprintf(
                    'No diff reader found for %s',
                    $upload->getProvider()->value
                )
            );
        }

        return $reader->get($upload);
    }
}
