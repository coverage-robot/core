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
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository',
                    'mock-ref',
                    'mock-commit',
                    1,
                    [],
                    []
                ),
                new ReportWaypoint(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository',
                    'mock-ref',
                    'mock-commit-2',
                    1,
                    [],
                    []
                ),
                true
            ],
            'Waypoints on different refs' => [
                new ReportWaypoint(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository',
                    'mock-ref',
                    'mock-commit',
                    null,
                    [],
                    []
                ),
                new ReportWaypoint(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository',
                    'mock-ref-2',
                    'mock-commit-2',
                    null,
                    [],
                    []
                ),
                true
            ],
            'Waypoints on same commit' => [
                new ReportWaypoint(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository',
                    'mock-ref',
                    'mock-commit',
                    5,
                    [],
                    []
                ),
                new ReportWaypoint(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository',
                    'mock-ref',
                    'mock-commit',
                    5,
                    [],
                    []
                ),
                true
            ],
            'Waypoints on different owners' => [
                new ReportWaypoint(
                    Provider::GITHUB,
                    'mock-owner-1',
                    'mock-repository',
                    'mock-ref',
                    'mock-commit',
                    null,
                    [],
                    []
                ),
                new ReportWaypoint(
                    Provider::GITHUB,
                    'mock-owner-2',
                    'mock-repository',
                    'mock-ref',
                    'mock-commit',
                    null,
                    [],
                    []
                ),
                false
            ],
            'Waypoints on different repositories' => [
                new ReportWaypoint(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository-1',
                    'mock-ref',
                    'mock-commit',
                    null,
                    [],
                    []
                ),
                new ReportWaypoint(
                    Provider::GITHUB,
                    'mock-owner',
                    'mock-repository-2',
                    'mock-ref',
                    'mock-commit',
                    null,
                    [],
                    []
                ),
                false
            ]
        ];
    }
}
