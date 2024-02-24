<?php

namespace App\Service\Publisher\Github;

use App\Exception\PullRequestNotFoundException;
use Symfony\Component\HttpFoundation\Response;

trait GithubPullRequestAwareTrait
{
    /**
     * Check if a given commit hash is still the head of a pull request.
     */
    private function isCommitStillPullRequestHead(
        string $owner,
        string $repository,
        int $pullRequest,
        string $commit
    ): bool {
        $details = $this->client->pullRequest()
            ->show(
                $owner,
                $repository,
                $pullRequest
            );

        if ($this->client->getLastResponse()?->getStatusCode() !== Response::HTTP_OK) {
            $this->reviewPublisherLogger->critical(
                sprintf(
                    '%s status code returned while attempting to get pull request details.',
                    (string)$this->client->getLastResponse()?->getStatusCode()
                )
            );

            throw new PullRequestNotFoundException(
                sprintf(
                    "Failed to fetch a pull request's details. Status code was %s",
                    (string)$this->client->getLastResponse()?->getStatusCode()
                )
            );
        }

        if (
            !isset($details['head']['sha']) ||
            !is_string($details['head']['sha'])
        ) {
            return true;
        }

        return $details['head']['sha'] === $commit;
    }
}
