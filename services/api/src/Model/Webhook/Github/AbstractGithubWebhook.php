<?php

namespace App\Model\Webhook\Github;

use App\Model\Webhook\AbstractWebhook;
use InvalidArgumentException;
use Packages\Models\Enum\Provider;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractGithubWebhook extends AbstractWebhook
{
    public const GITHUB_EVENT_HEADER = 'X-GitHub-Event';
    public const SIGNATURE_HEADER = 'x-hub-signature-256';
    public const SIGNATURE_ALGORITHM = 'sha256';

    /**
     * @throws InvalidArgumentException
     */
    public static function fromRequest(Provider $provider, Request $request): AbstractGithubWebhook
    {
        if (!$request->headers->has(self::GITHUB_EVENT_HEADER)) {
            throw new InvalidArgumentException('Provided request does not have an event header');
        }

        return match ($request->headers->get(self::GITHUB_EVENT_HEADER)) {
            'check_run' => GithubCheckRunWebhook::fromRequest($provider, $request),
            default => throw new InvalidArgumentException(
                sprintf(
                    'Invalid event type for Github webhook: %s',
                    $request->headers->get(self::GITHUB_EVENT_HEADER) ?? 'not provided'
                )
            ),
        };
    }
}
