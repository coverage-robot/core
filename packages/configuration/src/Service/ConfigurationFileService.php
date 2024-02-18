<?php

namespace Packages\Configuration\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Exception\InvalidSettingValueException;
use Packages\Contracts\Provider\Provider;
use Symfony\Component\Yaml\Yaml;
use WeakMap;

final class ConfigurationFileService
{
    public function __construct(
        private readonly SettingServiceInterface $settingService
    ) {
    }

    /**
     * Parse a raw configuration file into a validated set of settings.
     *
     * @return WeakMap<SettingKey, mixed>
     */
    public function parseFile(string $configurationFile): WeakMap
    {
        $parsedFile = Yaml::parse($configurationFile);

        $settings = $this->parseDotNotationKey($parsedFile ?? []);

        /** @var WeakMap<SettingKey, mixed> $parsedSettings */
        $parsedSettings = new WeakMap();

        foreach (SettingKey::cases() as $settingKey) {
            if (!isset($settings[$settingKey->value])) {
                continue;
            }

            try {
                $settingValue = $this->settingService->deserialize(
                    $settingKey,
                    $settings[$settingKey->value]
                );

                $parsedSettings[$settingKey] = $settingValue;
            } catch (InvalidSettingValueException) {
                continue;
            }
        }

        return $parsedSettings;
    }

    /**
     * Parse a raw configuration file into a validated set of settings and persist them
     * into the configuration store straight away.
     */
    public function parseAndPersistFile(
        Provider $provider,
        string $owner,
        string $repository,
        string $configurationFile
    ): bool {
        $successful = true;

        $settings = $this->parseFile($configurationFile);

        foreach (SettingKey::cases() as $settingKey) {
            $successful = match (isset($settings[$settingKey])) {
                true => $this->settingService->set(
                    $provider,
                    $owner,
                    $repository,
                    $settingKey,
                    $settings[$settingKey]
                ),
                false => $this->settingService->delete(
                    $provider,
                    $owner,
                    $repository,
                    $settingKey
                )
            } && $successful;
        }

        return $successful;
    }

    /**
     * Parse a multi-dimensional array into a flat, dot notation, equivalent.
     *
     * For example:
     * ```
     * [
     *      'foo' => [
     *          'bar' => 'baz'
     *      ],
     *      'baz' => [
     *          'qux' => ['a', 'b', 'c']
     *      ]
     * ]
     * ```
     * becomes:
     * ```
     * [
     *    'foo.bar' => 'baz',
     *    'baz.qux' => ['a', 'b', 'c']
     * ]
     * ```
     *
     * @param array<string, mixed> $yaml
     */
    private function parseDotNotationKey(array $yaml, string $prefix = ''): array
    {
        /** @var array<string, mixed> $results */
        $results = [];

        foreach ($yaml as $key => $value) {
            $key = sprintf('%s%s', $prefix, $key);

            if (
                is_array($value) &&
                !array_is_list($value) &&
                !SettingKey::tryFrom($key) instanceof SettingKey
            ) {
                $results = array_merge(
                    $results,
                    $this->parseDotNotationKey($value, $key . '.')
                );
                continue;
            }

            $results[$key] = $value;
        }

        return $results;
    }
}
