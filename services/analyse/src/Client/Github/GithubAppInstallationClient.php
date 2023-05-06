<?php

namespace App\Client\Github;

use Github\AuthMethod;
use Github\Client;
use OutOfBoundsException;
use UnexpectedValueException;

class GithubAppInstallationClient extends Client
{
    private ?string $owner = null;

    public function __construct(
        private readonly GithubAppClient $githubAppClient,
        ?string $owner = null
    ) {
        parent::__construct(
            $githubAppClient->getHttpClientBuilder(),
            $githubAppClient->getApiVersion(),
            $githubAppClient->enterpriseUrl
        );

        if ($owner !== null) {
            $this->authenticateAsRepositoryOwner($owner);
        }
    }

    public function authenticateAsRepositoryOwner(string $owner): void
    {
        if ($owner === $this->owner) {
            return;
        }

        $installation = $this->getInstallationForOwner($owner);

        $this->authenticateAsInstallation($installation);

        $this->owner = $owner;
    }

    private function authenticateAsInstallation(string $installationId): void
    {
        $accessToken = $this->githubAppClient->api('apps')
            ->createInstallationToken($installationId);

        if (!isset($accessToken["token"])) {
            throw new UnexpectedValueException("Unable to generate access token for installation.");
        }

        $this->authenticate($accessToken["token"], null, AuthMethod::ACCESS_TOKEN);
    }

    private function getInstallationForOwner(string $owner): string
    {
        $installs = array_filter(
            $this->githubAppClient->api('apps')->findInstallations(),
            static fn(array $install) => isset($install["id"]) &&
                isset($install["account"]["login"])
                && $install["account"]["login"] === $owner
        );

        if (empty($installs)) {
            throw new OutOfBoundsException("No installation with access to that account.");
        }

        /** @var string $id */
        $id = end($installs)["id"];

        return $id;
    }
}
