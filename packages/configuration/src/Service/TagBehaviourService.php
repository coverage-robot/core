<?php

namespace Packages\Configuration\Service;

use Packages\Configuration\Setting\DefaultTagBehaviourSetting;
use Packages\Configuration\Setting\IndividualTagBehavioursSetting;
use Packages\Contracts\Provider\Provider;

/**
 * A wrapper around the default, and individual tag behaviour settings, which helps
 * to simplify querying behaviours for a specific tag.
 */
class TagBehaviourService
{
    public function __construct(
        private readonly DefaultTagBehaviourSetting $defaultBehaviourSetting,
        private readonly IndividualTagBehavioursSetting $individualTagBehavioursSetting
    ) {
    }

    public function shouldCarryforwardTag(
        Provider $provider,
        string $owner,
        string $repository,
        string $tag
    ): bool {
        $individualBehaviours = $this->individualTagBehavioursSetting->get(
            $provider,
            $owner,
            $repository
        );

        foreach ($individualBehaviours as $behaviour) {
            if ($behaviour->getName() !== $tag) {
                continue;
            }

            // The tag has a tag-specific behaviour setting, so we should conform
            // to that
            return $behaviour->shouldCarryforward();
        }

        // No individual setting has been defined, so fallback to the default
        // behaviour
        $defaultBehaviour = $this->defaultBehaviourSetting->get(
            $provider,
            $owner,
            $repository
        );

        return $defaultBehaviour->shouldCarryforward();
    }
}
