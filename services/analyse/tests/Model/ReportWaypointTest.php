<?php

namespace App\Tests\Model;

use App\Model\ReportWaypoint;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReportWaypointTest extends TestCase
{
    public function testWaypointLazyLoading(): void
    {
        $waypoint = new ReportWaypoint(
            provider: Provider::GITHUB,
            owner: 'mock-owner',
            repository: 'mock-repository',
            ref: 'mock-ref',
            commit: 'mock-commit',
            history: static fn () => [],
            diff: static fn () => [],
            ingestTimes: static fn () => []
        );

        $this->assertEquals(Provider::GITHUB, $waypoint->getProvider());
        $this->assertEquals('mock-owner', $waypoint->getOwner());
        $this->assertEquals('mock-repository', $waypoint->getRepository());
        $this->assertEquals('mock-ref', $waypoint->getRef());
        $this->assertEquals('mock-commit', $waypoint->getCommit());
        $this->assertEquals([], $waypoint->getHistory());
        $this->assertEquals([], $waypoint->getDiff());
        $this->assertEquals([], $waypoint->getIngestTimes());
    }

    #[DataProvider('waypointProvider')]
    public function testComparable(
        ReportWaypoint $waypoint1,
        ReportWaypoint $waypoint2,
        bool $expected
    ): void {
        $this->assertEquals(
            $expected,
            $waypoint1->comparable($waypoint2)
        );
    }

    public static function waypointProvider(): array
    {
        return [
            'Waypoints on same ref' => [
                new ReportWaypoint(
                    provider: Provider::GITHUB,
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
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit-2',
                    history: [],
                    diff: [],
                    pullRequest: 1
                ),
                true
            ],
            'Waypoints on different refs' => [
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref-2',
                    commit: 'mock-commit-2',
                    history: [],
                    diff: []
                ),
                true
            ],
            'Waypoints on same commit' => [
                new ReportWaypoint(
                    provider: Provider::GITHUB,
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
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: [],
                    pullRequest: 5
                ),
                true
            ],
            'Waypoints on different owners' => [
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner-1',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner-2',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                false
            ],
            'Waypoints on different repositories' => [
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository-1',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository-2',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    history: [],
                    diff: []
                ),
                false
            ]
        ];
    }
}
