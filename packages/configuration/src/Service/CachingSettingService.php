<?php

namespace Packages\Configuration\Service;

use Override;
use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CachingSettingService implements SettingServiceInterface
{
    /**
     * A simple in-memory cache for settings which get called
     * repeatedly in quick succession.
     *
     * @var array<string, mixed>
     */
    private array $cache = [];

    public function __construct(
        #[Autowire(service: SettingService::class)]
        private readonly SettingServiceInterface $settingService
    ) {
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function get(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): mixed {
        $cacheKey = $this->getCacheKey($key, $provider, $owner, $repository);

        if (!isset($this->cache[$cacheKey])) {
            $this->cache[$cacheKey] = $this->settingService->get(
                $provider,
                $owner,
                $repository,
                $key
            );
        }

        return $this->cache[$cacheKey];
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
        $cacheKey = $this->getCacheKey($key, $provider, $owner, $repository);

        if (
            isset($this->cache[$cacheKey]) &&
            $this->cache[$cacheKey] === $value
        ) {
            // The cache already reflects the value we're trying to store, so we
            // can be confident that the value is already stored in DynamoDB
            return true;
        }

        $isSuccessful = $this->settingService->set(
            $provider,
            $owner,
            $repository,
            $key,
            $value
        );

        if ($isSuccessful) {
            $this->cache[$cacheKey] = $value;
        }

        return $isSuccessful;
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
        return $this->settingService->delete(
            $provider,
            $owner,
            $repository,
            $key
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
        return $this->settingService->deserialize(
            $key,
            $value
        );
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
