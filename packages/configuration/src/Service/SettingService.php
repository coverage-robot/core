<?php

namespace Packages\Configuration\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Setting\SettingInterface;
use Packages\Contracts\Provider\Provider;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class SettingService
{
    public function __construct(
        #[TaggedIterator(
            'app.settings',
            defaultIndexMethod: 'getSettingKey'
        )]
        private readonly iterable $settings
    ) {
    }

    /**
     * Get a setting's value, for a specific repository, from the configuration store.
     */
    public function get(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): mixed {
        return $this->getSetting($key)
            ->get(
                $provider,
                $owner,
                $repository
            );
    }

    /**
     * Set the state of a setting for a particular repository in the configuration store.
     */
    public function set(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key,
        mixed $value
    ): bool {
        return $this->getSetting($key)
            ->set(
                $provider,
                $owner,
                $repository,
                $value
            );
    }

    public function delete(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): bool {
        return $this->getSetting($key)
            ->delete(
                $provider,
                $owner,
                $repository
            );
    }

    /**
     * Validate the value of a setting.
     */
    public function validate(
        SettingKey $key,
        mixed $value
    ): bool {
        return $this->getSetting($key)
            ->validate($value);
    }

    /**
     * Get the class implementation representing a particular setting which a user
     * can configure.
     */
    private function getSetting(SettingKey $key): SettingInterface
    {
        $resolver = (iterator_to_array($this->settings)[$key->value]) ?? null;

        if (!$resolver instanceof SettingInterface) {
            throw new RuntimeException(
                sprintf(
                    'No setting found for %s',
                    $key->value
                )
            );
        }

        return $resolver;
    }
}
