<?php

namespace Packages\Configuration\Tests\Service;

use Packages\Configuration\Model\DefaultTagBehaviour;
use Packages\Configuration\Model\IndividualTagBehaviour;
use Packages\Configuration\Service\TagBehaviourService;
use Packages\Configuration\Setting\DefaultTagBehaviourSetting;
use Packages\Configuration\Setting\IndividualTagBehavioursSetting;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TagBehaviourServiceTest extends TestCase
{
    #[DataProvider('behavioursDataProvider')]
    public function testShouldCarryforwardTag(
        DefaultTagBehaviour $defaultTagBehaviour,
        array $individualTagBehaviours,
        bool $expectedCarryforwardBehaviour
    ): void {
        $mockDefaultTagBehaviourSetting = $this->createMock(DefaultTagBehaviourSetting::class);
        $mockDefaultTagBehaviourSetting->expects($this->atMost(1))
            ->method('get')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository'
            )
            ->willReturn($defaultTagBehaviour);

        $mockIndividualTagBehavioursSetting = $this->createMock(IndividualTagBehavioursSetting::class);
        $mockIndividualTagBehavioursSetting->expects($this->atMost(1))
            ->method('get')
            ->with(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository'
            )
            ->willReturn($individualTagBehaviours);

        $tagBehaviourService = new TagBehaviourService(
            $mockDefaultTagBehaviourSetting,
            $mockIndividualTagBehavioursSetting
        );

        $this->assertEquals(
            $expectedCarryforwardBehaviour,
            $tagBehaviourService->shouldCarryforwardTag(
                Provider::GITHUB,
                'mock-owner',
                'mock-repository',
                'mock-tag'
            )
        );
    }

    public static function behavioursDataProvider(): array
    {
        return [
            "Only default behaviour (turned off)" => [
                new DefaultTagBehaviour(
                    carryforward: false
                ),
                [],
                false
            ],
            "Only default behaviour (turned on)" => [
                new DefaultTagBehaviour(
                    carryforward: true
                ),
                [],
                true
            ],
            "No matching individual tag behaviours" => [
                new DefaultTagBehaviour(
                    carryforward: true
                ),
                [
                    new IndividualTagBehaviour(
                        'not-related-tag',
                        false
                    ),
                    new IndividualTagBehaviour(
                        'not-related-tag-2',
                        false
                    )
                ],
                true
            ],
            "Matching individual tag behaviours" => [
                new DefaultTagBehaviour(
                    carryforward: false
                ),
                [
                    new IndividualTagBehaviour(
                        'not-related-tag',
                        false
                    ),
                    new IndividualTagBehaviour(
                        'mock-tag',
                        true
                    )
                ],
                true
            ],
        ];
    }
}
