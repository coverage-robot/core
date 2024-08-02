<?php

namespace Packages\Configuration\Mock;

use Packages\Configuration\Service\SettingServiceInterface;
use Override;

class MockSettingService implements SettingServiceInterface
{
    public function __construct(
        private readonly array $settings = []
    ) {
    }

    #[Override]
    public function get(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): mixed {
        return $this->settings[$key] ?? null;
    }

    #[Override]
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

    #[Override]
    public function delete(
        Provider $provider,
        string $owner,
        string $repository,
        SettingKey $key
    ): bool {
        unset($this->settings[$key]);
        
        return true;
    }

    #[Override]
    public function deserialize(
        SettingKey $key,
        mixed $value
    ): mixed {
        return $this->settings[$key] ?? null;
    }
}
