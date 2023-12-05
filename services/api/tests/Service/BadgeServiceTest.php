<?php

namespace App\Tests\Service;

use App\Service\BadgeService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;

class BadgeServiceTest extends TestCase
{
    #[DataProvider('projectCoverageDataProvider')]
    public function testBadgeCreation(?float $coveragePercentage, string $expectedHex, float $expectedValueWidth): void
    {
        $mockTwigEnvironment = $this->createMock(Environment::class);

        $mockTwigEnvironment->expects($this->once())
            ->method('render')
            ->with(
                'badges/badge.svg.twig',
                [
                    'fontFamily' => 'dejavu sans',
                    'label' => BadgeService::BADGE_LABEL,
                    'iconWidth' => 15,
                    'labelWidth' => 51.068359375,
                    'valueWidth' => $expectedValueWidth,
                    'value' => $coveragePercentage ?
                        sprintf('%s%%', $coveragePercentage) :
                        BadgeService::NO_COVERGAGE_PERCENTAGE_VALUE,
                    'color' => $expectedHex
                ]
            )
            ->willReturn('<svg></svg>');

        $badgeService = new BadgeService(
            $mockTwigEnvironment,
            'templates/'
        );

        $this->assertEquals(
            '<svg></svg>',
            $badgeService->renderCoveragePercentageBadge($coveragePercentage)
        );
    }

    public static function projectCoverageDataProvider(): array
    {
        return [
            [
                99,
                '05ff00',
                24.44921875
            ],
            [
                1,
                'ff0500',
                17.45068359375
            ],
            [
                100,
                '00ff00',
                31.44775390625
            ],
            [
                null,
                'ff0000',
                49.9833984375
            ],
            [
                34.64,
                'ffb100',
                41.94287109375
            ]
        ];
    }
}
