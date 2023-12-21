<?php

namespace App\Tests\Model;

use App\Model\ReportWaypoint;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ReportWaypointTest extends TestCase
{
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
                    pullRequest: 1,
                    history: [],
                    diff: []
                ),
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit-2',
                    pullRequest: 1,
                    history: [],
                    diff: []
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
                    pullRequest: 5,
                    history: [],
                    diff: []
                ),
                new ReportWaypoint(
                    provider: Provider::GITHUB,
                    owner: 'mock-owner',
                    repository: 'mock-repository',
                    ref: 'mock-ref',
                    commit: 'mock-commit',
                    pullRequest: 5,
                    history: [],
                    diff: []
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
