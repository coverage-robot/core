<?php

declare(strict_types=1);

namespace Packages\Configuration\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Model\DefaultTagBehaviour;
use Packages\Configuration\Model\IndividualTagBehaviour;
use Packages\Contracts\Provider\Provider;

/**
 * A wrapper around the default, and individual tag behaviour settings, which helps
 * to simplify querying behaviours for a specific tag.
 */
final readonly class TagBehaviourService
{
    public function __construct(
        private SettingServiceInterface $settingService
    ) {
    }

    public function shouldCarryforwardTag(
        Provider $provider,
        string $owner,
        string $repository,
        string $tag
    ): bool {
        /** @var IndividualTagBehaviour[] $individualBehaviours */
        $individualBehaviours = $this->settingService->get(
            $provider,
            $owner,
            $repository,
            SettingKey::INDIVIDUAL_TAG_BEHAVIOURS
        );

        foreach ($individualBehaviours as $individualBehaviour) {
            if ($individualBehaviour->getName() !== $tag) {
                continue;
            }

            // The tag has a tag-specific behaviour setting, so we should conform
            // to that
            return $individualBehaviour->getCarryforward();
        }

        // No individual setting has been defined, so fallback to the default
        // behaviour
        /** @var DefaultTagBehaviour $defaultBehaviour */
        $defaultBehaviour = $this->settingService->get(
            $provider,
            $owner,
            $repository,
            SettingKey::DEFAULT_TAG_BEHAVIOUR
        );

        return $defaultBehaviour->getCarryforward();
    }
}
