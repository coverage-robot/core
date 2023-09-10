<?php

namespace App\Model\Webhook\Github;

use App\Model\Webhook\AbstractWebhook;
use InvalidArgumentException;
use Packages\Models\Enum\Provider;

abstract class AbstractGithubWebhook extends AbstractWebhook
{
    /**
     * @throws InvalidArgumentException
     */
    public static function fromBody(Provider $provider, array $body): AbstractGithubWebhook
    {
        if (
            !is_string($body['event'] ?? null) ||
            !is_array($body['payload'] ?? null)
        ) {
            throw new InvalidArgumentException('Provided body is not a valid Github webhook');
        }

        return match ($body['event']) {
            'check_run' => GithubCheckRunWebhook::fromBody($provider, (array)$body['payload']),
            default => throw new InvalidArgumentException(
                sprintf(
                    'Invalid event type for Github webhook: %s',
                    (string)$body['event']
                )
            ),
        };
    }
}
