<?php

declare(strict_types=1);

namespace App\Service\History;

use App\Exception\CommitHistoryException;
use App\Model\ReportWaypoint;
use Override;
use Packages\Contracts\Provider\ProviderAwareInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class CommitHistoryService implements CommitHistoryServiceInterface
{
    /**
     * The total number of commits which should be returned per page.
     *
     * GitHub will accept requesting a maximum of 100 commits per page, however
     * they're prone to timeouts and other transient issues when handling large
     * numbers of results.
     *
     * With this in mind, we've opted to request 50 commits per page, to try and
     * balance the number of requests we need to make, with the likelihood of encountering
     * a timeout.
     *
     * @see https://docs.github.com/en/rest/using-the-rest-api/troubleshooting-the-rest-api?apiVersion=2022-11-28#timeouts
     * @see https://docs.github.com/en/graphql/overview/rate-limits-and-node-limits-for-the-graphql-api#timeouts
     */
    public const int COMMITS_TO_RETURN_PER_PAGE = 50;

    /**
     * @param (CommitHistoryServiceInterface&ProviderAwareInterface)[] $parsers
     */
    public function __construct(
        #[AutowireIterator(
            'app.commit_history',
            defaultIndexMethod: 'getProvider'
        )]
        private readonly iterable $parsers
    ) {
    }

    #[Override]
    public function getPrecedingCommits(ReportWaypoint $waypoint, int $page = 1): array
    {
        $service = (iterator_to_array($this->parsers)[$waypoint->getProvider()->value]) ?? null;

        if (!$service instanceof CommitHistoryServiceInterface) {
            throw new CommitHistoryException(
                sprintf(
                    'No commit history service for %s',
                    $waypoint->getProvider()->value
                )
            );
        }

        return $service->getPrecedingCommits($waypoint, $page);
    }
}
