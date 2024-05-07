<?php

namespace Packages\Configuration\Service;

use Override;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Setting\SettingInterface;
use Packages\Contracts\Provider\Provider;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class SettingService implements SettingServiceInterface
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
     * @inheritDoc
     * @throws InvalidSettingValueException
     */
    #[Override]
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
     * @inheritDoc
     *
     * @throws InvalidSettingValueException
     */
    #[Override]
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

    /**
     * @inheritDoc
     */
    #[Override]
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
     * @inheritDoc
     *
     * @throws InvalidSettingValueException
     */
    #[Override]
    public function deserialize(
        SettingKey $key,
        mixed $value
    ): mixed {
        return $this->getSetting($key)
            ->deserialize($value);
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
