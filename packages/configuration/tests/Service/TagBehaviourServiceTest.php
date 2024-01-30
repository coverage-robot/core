<?php

namespace Packages\Configuration\Tests\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Mock\MockSettingServiceFactory;
use Packages\Configuration\Model\DefaultTagBehaviour;
use Packages\Configuration\Model\IndividualTagBehaviour;
use Packages\Configuration\Service\TagBehaviourService;
use Packages\Contracts\Provider\Provider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TagBehaviourServiceTest extends TestCase
{
    #[DataProvider('behavioursDataProvider')]
    public function testShouldCarryforwardTag(
        DefaultTagBehaviour $defaultTagBehaviour,
        array $individualTagBehaviours,
        bool $expectedCarryforwardBehaviour
    ): void {
        $mockSettingService = MockSettingServiceFactory::createMock(
            $this,
            [
                SettingKey::DEFAULT_TAG_BEHAVIOUR->value => $defaultTagBehaviour,
                SettingKey::INDIVIDUAL_TAG_BEHAVIOURS->value => $individualTagBehaviours
            ]
        );

        $tagBehaviourService = new TagBehaviourService($mockSettingService);

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
