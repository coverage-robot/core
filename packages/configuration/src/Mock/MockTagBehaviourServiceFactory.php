<?php

namespace Packages\Configuration\Mock;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Model\DefaultTagBehaviour;
use Packages\Configuration\Model\IndividualTagBehaviour;
use Packages\Configuration\Service\TagBehaviourService;
use PHPUnit\Framework\TestCase;

final class MockTagBehaviourServiceFactory
{
    public static function createMock($tagsToCarryforward = []): TagBehaviourService
    {
        $mockSettingService = MockSettingServiceFactory::createMock(
            [
                SettingKey::DEFAULT_TAG_BEHAVIOUR->value => new DefaultTagBehaviour(false),
                SettingKey::INDIVIDUAL_TAG_BEHAVIOURS->value => array_map(
                    static fn (string $tag): IndividualTagBehaviour => new IndividualTagBehaviour(
                        $tag,
                        $tagsToCarryforward[$tag] ?? false
                    ),
                    array_keys($tagsToCarryforward)
                )
            ]
        );

        return new TagBehaviourService($mockSettingService);
    }
}
