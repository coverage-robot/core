<?php

namespace App\Tests\App\Service;

use App\Entity\Project;
use App\Service\BadgeService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PUGX\Poser\Poser;

class BadgeServiceTest extends TestCase
{
    #[DataProvider('projectCoverageDataProvider')]
    public function testBadgeCreation(?float $coveragePercentage, string $expectedHex): void
    {
        $poser = $this->createMock(Poser::class);

        $poser->expects($this->once())
            ->method('generate')
            ->with(BadgeService::BADGE_LABEL, $coveragePercentage !== null ?
                sprintf("%s%%", number_format($coveragePercentage, 2)) :
                "unknown", $expectedHex, 'flat');

        $badgeService = new BadgeService($poser);

        $mockProject = $this->createMock(Project::class);
        $mockProject->expects($this->atLeastOnce())
            ->method('getCoveragePercentage')
            ->willReturn($coveragePercentage);

        $badgeService->getBadge($mockProject);
    }

    public static function projectCoverageDataProvider(): array
    {
        return [
            [
                99,
                "05ff00"
            ],
            [
                1,
                "ff0500"
            ],
            [
                100,
                "00ff00"
            ],
            [
                null,
                "ff0000"
            ],
            [
                34.64,
                "ffb100"
            ]
        ];
    }
}
