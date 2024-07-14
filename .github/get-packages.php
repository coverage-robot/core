<?php
/**
 * This script allows us to dynamically retrieve the package namespace
 * and directory from our composer file. This is then used by our GitHub
 * action to publish the packages to their appropriate repositories.
 *
 * @link https://github.com/ecotoneframework/ecotone-dev/blob/main/bin/get-packages
 */

const PACKAGES_DIRECTORY = __DIR__ . '/../packages/';

function getPackageNameFromComposerFile(string $composerFile)
{
    $composer = json_decode(file_get_contents($composerFile), true);

    if (!isset($composer['name'])) {
        throw new UnexpectedValueException('The referenced package is invalid because it is missing a name: ' . $composerFile);
    }

    return str_replace('coverage-robot/', '', $composer['name']);
}

/**
 * @return array<array-key, array{
 *     directory: string,
 *     name: string,
 *     package: string,
 *     organization: string,
 *     repository: string
 * }>
 */
function getPackages(): array
{
    $packages = [];
    $directoryIterator = new DirectoryIterator(realpath(PACKAGES_DIRECTORY));

    /**
     * @var DirectoryIterator $directory
     */
    foreach ($directoryIterator as $directory) {
        if ($directory->isDot()) {
            continue;
        }

        $file = $directory->getRealPath() . DIRECTORY_SEPARATOR . 'composer.json';

        if (! file_exists($file)) {
            continue;
        }

        $name = getPackageNameFromComposerFile($file);
        $packages[] = [
            'directory'  => $directory->getRealPath(),
            'name' => $name,
            'package' => 'coverage-robot/' . $name,
            'organization' => 'coverage-robot',
            'repository' => $name,
        ];
    }

    return $packages;
}

echo json_encode(getPackages());
