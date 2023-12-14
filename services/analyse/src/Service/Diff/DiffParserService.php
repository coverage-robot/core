<?php

namespace App\Service\Diff;

use App\Model\ReportWaypoint;
use App\Service\ProviderAwareInterface;
use Packages\Contracts\Event\EventInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class DiffParserService implements DiffParserServiceInterface
{
    /**
     * @param (DiffParserServiceInterface&ProviderAwareInterface)[] $parsers
     */
    public function __construct(
        #[TaggedIterator(
            'app.diff_parser',
            defaultIndexMethod: 'getProvider',
            exclude: ['CachingDiffParserService', 'DiffParserService']
        )]
        private readonly iterable $parsers
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(EventInterface|ReportWaypoint $event): array
    {
        $parser = (iterator_to_array($this->parsers)[$event->getProvider()->value]) ?? null;

        if (!$parser instanceof DiffParserServiceInterface) {
            throw new RuntimeException(
                sprintf(
                    'No diff parser found for %s',
                    $event->getProvider()->value
                )
            );
        }

        return $parser->get($event);
    }
}
