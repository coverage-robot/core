<?php

declare(strict_types=1);

namespace App\Service\Diff;

use App\Exception\CommitDiffException;
use App\Model\ReportWaypoint;
use Override;
use Packages\Contracts\Provider\ProviderAwareInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class DiffParserService implements DiffParserServiceInterface
{
    /**
     * @param (DiffParserServiceInterface&ProviderAwareInterface)[] $parsers
     */
    public function __construct(
        #[AutowireIterator(
            'app.diff_parser',
            defaultIndexMethod: 'getProvider',
            exclude: ['CachingDiffParserService', 'DiffParserService']
        )]
        private iterable $parsers
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(ReportWaypoint $waypoint): array
    {
        $parser = (iterator_to_array($this->parsers)[$waypoint->getProvider()->value]) ?? null;

        if (!$parser instanceof DiffParserServiceInterface) {
            throw new CommitDiffException(
                sprintf(
                    'No diff parser found for %s',
                    $waypoint->getProvider()->value
                )
            );
        }

        return $parser->get($waypoint);
    }
}
