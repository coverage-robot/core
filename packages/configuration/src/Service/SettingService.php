<?php

namespace Packages\Configuration\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Configuration\Setting\SettingInterface;
use Packages\Contracts\Provider\Provider;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

final class SettingService implements SettingServiceInterface
{
    /**
     * A simple in-memory cache for settings which get called
     * repeatedly in quick succession.
     *
     * @var array<string, mixed>
     */
    private array $cache = [];

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
     */
    public function get(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): mixed {
        $cacheKey = $this->getCacheKey($key, $provider, $owner, $repository);

        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->getSetting($key)
                ->get(
                    $provider,
                    $owner,
                    $repository
                );
        }

        return $this->cache[$cacheKey];
    }

    /**
     * @inheritDoc
     *
     * @throws InvalidSettingValueException
     */
    public function set(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key,
        mixed $value
    ): bool {
        $cacheKey = $this->getCacheKey($key, $provider, $owner, $repository);

        $setting = $this->getSetting($key);
        $setting->validate($value);

        if (
            isset($this->cache[$cacheKey]) &&
            $this->cache[$cacheKey] === $value
        ) {
            // The cache already reflects the value we're trying to store, so we
            // can be confident that the value is already stored in DynamoDB
            return true;
        }

        $isSuccessful = $setting->set(
            $provider,
            $owner,
            $repository,
            $value
        );

        if ($isSuccessful) {
            $this->cache[$cacheKey] = $value;
        }

        return $isSuccessful;
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
     * @inheritDoc
     *
     * @throws InvalidSettingValueException
     */
    public function deserialize(
        SettingKey $key,
        mixed $value
    ): mixed {
        $setting = $this->getSetting($key);

        $value = $setting->deserialize($value);

        $setting->validate($value);

        return $value;
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

    /**
     * Generate a unique cache key for a setting, so that it can be stored in memory
     * so repeated setting requests do not all call out to DynamoDb
     */
    private function getCacheKey(
        SettingKey $key,
        Provider $provider,
        string $owner,
        string $repository
    ): string {
        return md5(
            implode(
                "",
                [
                    $key->value,
                    $provider->value,
                    $owner,
                    $repository
                ]
            )
        );
    }
}
