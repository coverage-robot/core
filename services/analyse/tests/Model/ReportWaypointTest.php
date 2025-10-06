<?php

declare(strict_types=1);

namespace App\Tests\Model;

use App\Model\ReportWaypoint;
use Iterator;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReportWaypointTest extends TestCase
{
    public function testWaypointLazyLoading(): void
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            projectId: 'mock-project',
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: static fn(): array => [],
            diff: static fn(): array => []
        );

        $this->assertSame(Provider::GITHUB, $waypoint->getProvider());
        $this->assertSame('mock-owner', $waypoint->getOwner());
        $this->assertSame('mock-repository', $waypoint->getRepository());
        $this->assertSame('mock-ref', $waypoint->getRef());
        $this->assertSame('mock-commit', $waypoint->getCommit());
        $this->assertSame([], $waypoint->getHistory());
        $this->assertSame([], $waypoint->getDiff());
    }

    #[DataProvider('waypointProvider')]
    public function testComparable(
        ReportWaypoint $waypoint1,
        ReportWaypoint $waypoint2,
        bool $expected
    ): void {
        $this->assertSame(
            $expected,
            $waypoint1->comparable($waypoint2)
        );
    }

    /**
     * @return Iterator<string, list{ ReportWaypoint, ReportWaypoint, bool}>
     */
    public static function waypointProvider(): Iterator
    {
        yield 'Waypoints on same ref' => [
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: [],
                pullRequest: 1
            ),
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit-2',
                history: [],
                diff: [],
                pullRequest: 1
            ),
            true
        ];

        yield 'Waypoints on different refs' => [
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: []
            ),
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref-2',
                commit: 'mock-commit-2',
                history: [],
                diff: []
            ),
            true
        ];

        yield 'Waypoints on same commit' => [
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: [],
                pullRequest: 5
            ),
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: [],
                pullRequest: 5
            ),
            true
        ];

        yield 'Waypoints on different owners' => [
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner-1',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: []
            ),
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner-2',
                repository: 'mock-repository',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: []
            ),
            false
        ];

        yield 'Waypoints on different repositories' => [
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner',
                repository: 'mock-repository-1',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: []
            ),
            new ReportWaypoint(
                provider: Provider::GITHUB,
                projectId: 'mock-project',
                owner: 'mock-owner',
                repository: 'mock-repository-2',
                ref: 'mock-ref',
                commit: 'mock-commit',
                history: [],
                diff: []
            ),
            false
        ];
    }
}
