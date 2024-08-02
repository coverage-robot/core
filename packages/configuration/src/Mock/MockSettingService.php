<?php

namespace Packages\Configuration\Mock;

use Packages\Configuration\Service\SettingServiceInterface;

class MockSettingService implements SettingServiceInterface
{
    public function __construct(
        private readonly array $settings = []
    ) {
    }

    public function get(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): mixed {
        return $this->settings[$key] ?? null;
    }

    public function set(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key,
        mixed $value
    ): bool {
        $this->settings[$key] = $value;

        return true;
    }

    public function delete(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): bool {
        unset($this->settings[$key]);
        
        return true;
    }

    public function deserialize(
        SettingKey $key,
        mixed $value
    ): mixed {
        return $this->settings[$key] ?? null;
    }
}
