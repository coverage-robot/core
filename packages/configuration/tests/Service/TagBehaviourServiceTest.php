<?php

namespace Packages\Configuration\Tests\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Model\DefaultTagBehaviour;
use Packages\Configuration\Model\IndividualTagBehaviour;
use Packages\Configuration\Service\SettingServiceInterface;
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
        $settings = [
            SettingKey::DEFAULT_TAG_BEHAVIOUR->value => $defaultTagBehaviour,
            SettingKey::INDIVIDUAL_TAG_BEHAVIOURS->value => $individualTagBehaviours
        ];

        $mockSettingService = $this->createMock(SettingServiceInterface::class);

        $mockSettingService->expects($this->atMost(2))
            ->method('get')
            ->willReturnCallback(
                static fn (
                    Provider $provider,
                    string $owner,
                    string $repository,
                    SettingKey $key
                ): DefaultTagBehaviour|array|null => $settings[$key->value] ?? null
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
