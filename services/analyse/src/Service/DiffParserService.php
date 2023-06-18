<?php

namespace App\Service;

use App\Service\Diff\DiffParserServiceInterface;
use Packages\Models\Model\Upload;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class DiffParserService
{
    /**
     * @param array<array-key, DiffParserServiceInterface> $readers
     */
    public function __construct(
        #[TaggedIterator('app.diff_reader', defaultIndexMethod: 'getProvider')]
        private readonly iterable $readers
    ) {
    }

    public function get(Upload $upload): array
    {
        $reader = (iterator_to_array($this->readers)[$upload->getProvider()->value]) ?? null;

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
