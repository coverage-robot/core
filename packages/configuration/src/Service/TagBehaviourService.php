<?php

namespace Packages\Configuration\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Contracts\Provider\Provider;

/**
 * A wrapper around the default, and individual tag behaviour settings, which helps
 * to simplify querying behaviours for a specific tag.
 */
class TagBehaviourService
{
    public function __construct(
        private readonly SettingService $settingService
    ) {
    }

    public function shouldCarryforwardTag(
        Provider $provider,
        string $owner,
        string $repository,
        string $tag
    ): bool {
        $individualBehaviours = $this->settingService->get(
            $provider,
            $owner,
            $repository,
            SettingKey::INDIVIDUAL_TAG_BEHAVIOURS
        );

        foreach ($individualBehaviours as $behaviour) {
            if ($behaviour->getName() !== $tag) {
                continue;
            }

            // The tag has a tag-specific behaviour setting, so we should conform
            // to that
            return $behaviour->getCarryforward();
        }

        // No individual setting has been defined, so fallback to the default
        // behaviour
        $defaultBehaviour = $this->settingService->get(
            $provider,
            $owner,
            $repository,
            SettingKey::DEFAULT_TAG_BEHAVIOUR
        );

        return $defaultBehaviour->getCarryforward();
    }
}
