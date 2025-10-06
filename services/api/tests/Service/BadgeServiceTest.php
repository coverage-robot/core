<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\BadgeService;
use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class BadgeServiceTest extends KernelTestCase
{
    use MatchesSnapshots;

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
                        sprintf(
                            '%s%%',
                            floor($coveragePercentage) !== $coveragePercentage ?
                                number_format($coveragePercentage, 2, '.', '') :
                                $coveragePercentage
                        ) :
                        BadgeService::NO_COVERGAGE_PERCENTAGE_VALUE,
                    'color' => $expectedHex
                ]
            )
            ->willReturn('<svg></svg>');

        $badgeService = new BadgeService(
            $mockTwigEnvironment,
            'templates/'
        );

        $this->assertSame(
            '<svg></svg>',
            $badgeService->renderCoveragePercentageBadge($coveragePercentage)
        );
    }

    #[DataProvider('projectCoverageWithIconsDataProvider')]
    public function testBadgeRendering(
        ?float $coveragePercentage,
        bool $includeIcon
    ): void {
        /** @var BadgeService $badgeService */
        $badgeService = $this->getContainer()
            ->get(BadgeService::class);

        $this->assertMatchesXmlSnapshot(
            $badgeService->renderCoveragePercentageBadge(
                $coveragePercentage,
                $includeIcon
            )
        );
    }

    /**
     * @return Iterator<list{ float|null, string, float }>
     */
    public static function projectCoverageDataProvider(): Iterator
    {
        yield [
            99,
            '05ff00',
            24.44921875
        ];

        yield [
            1,
            'ff0500',
            17.45068359375
        ];

        yield [
            100,
            '00ff00',
            31.44775390625
        ];

        yield [
            null,
            'ff0000',
            49.9833984375
        ];

        yield [
            34.64,
            'ffb100',
            41.94287109375
        ];

        yield [
            34.60,
            'ffb000',
            41.94287109375
        ];
    }

    /**
     * @return array<string, list{ float|null, bool }>
     */
    public static function projectCoverageWithIconsDataProvider(): array
    {
        return array_reduce(
            iterator_to_array(self::projectCoverageDataProvider()),
            static fn (array $carry, array $item): array => [
                ...$carry,
                sprintf('%s with icons', $item[0] ? $item[0] . '%' : 'null') => [
                    $item[0],
                    true
                ],
                sprintf('%s without icons', $item[0] ? $item[0] . '%' : 'null') => [
                    $item[0],
                    false
                ]
            ],
            []
        );
    }
}
