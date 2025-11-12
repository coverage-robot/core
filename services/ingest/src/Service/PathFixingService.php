<?php

declare(strict_types=1);

namespace App\Service;

use Packages\Configuration\Enum\SettingKey;
use Packages\Configuration\Model\PathReplacement;
use Packages\Configuration\Service\SettingServiceInterface;
use Packages\Contracts\Provider\Provider;

final readonly class PathFixingService
{
    public function __construct(
        private SettingServiceInterface $settingService
    ) {
    }

    /**
     * Remove the root of a path, if present. This is designed for making the paths
     * inside a coverage file relative to the Git project root during parsing.
     *
     * This is achieved in two ways (in order of application):
     * 1. Applying any user-defined path replacements to the path (i.e. removing and replacing values
     *    as defined in the configuration).
     * 2. Removing the project root from the path, if present.
     *
     * This applies the project root removal second because then the path replacements will
     * behave more predictably when looking at the raw coverage files.
     */
    public function fixPath(
        Provider $provider,
        string $owner,
        string $repository,
        string $path,
        string $projectRoot
    ): string {
        $path = $this->removeUsingPathReplacements($provider, $owner, $repository, $path);

        $path = $this->removeUsingProjectRoot($path, $projectRoot);

        $path = $this->removeUsingRepositoryUrl($provider, $owner, $repository, $path);

        return trim($path, '/');
    }

    /**
     * Apply any user-defined path replacements to a path.
     */
    private function removeUsingPathReplacements(
        Provider $provider,
        string $owner,
        string $repository,
        string $path
    ): string {
        /** @var PathReplacement[] $pathReplacements */
        $pathReplacements = $this->settingService->get(
            $provider,
            $owner,
            $repository,
            SettingKey::PATH_REPLACEMENTS
        );

        foreach ($pathReplacements as $pathReplacement) {
            $regex = str_replace('/', '\/', $pathReplacement->getBefore());

            $replacement = preg_replace(
                '/' . $regex . '/is',
                $pathReplacement->getAfter() ?? '',
                $path
            );

            if (is_string($replacement) && $path !== $replacement) {
                // The path replacement matched, and the string has been modified,
                // so theres no need to apply any other path replacements.
                $path = $replacement;
                break;
            }
        }

        return $path;
    }

    /**
     * Remove the project root from a path, if present.
     */
    private function removeUsingProjectRoot(
        string $path,
        string $projectRoot
    ): string {
        if (str_starts_with($path, $projectRoot)) {
            return substr($path, strlen($projectRoot));
        }

        return $path;
    }

    /**
     * Remove the repository URL from a path, if present.
     *
     * This is largely relevant for Go, which has a convention of naming the module as
     * `github.com/<owner>/<repository>/.../*.go`, where the owner and repository are the
     * same as those hosted on the VCS provider.
     *
     * This doesn't handle aliased repository URLs (which will vary depending on package). If those
     * are used, path replacements are a great alternative.
     *
     * @see https://go.dev/ref/mod#modules-overview
     */
    private function removeUsingRepositoryUrl(
        Provider $provider,
        string $owner,
        string $repository,
        string $path
    ): string {
        $repositoryUrl = null;
        if ($provider == Provider::GITHUB) {
            $repositoryUrl = sprintf(
                'github.com/%s/%s',
                $owner,
                $repository
            );
        }

        if ($repositoryUrl === null) {
            return $path;
        }

        if (str_starts_with($path, $repositoryUrl)) {
            return substr($path, strlen($repositoryUrl));
        }

        return $path;
    }
}
