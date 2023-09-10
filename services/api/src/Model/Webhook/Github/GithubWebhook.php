<?php

namespace App\Model\Webhook\Github;

use App\Model\Webhook\AbstractWebhook;
use InvalidArgumentException;
use Packages\Models\Enum\Provider;

abstract class GithubWebhook extends AbstractWebhook
{
    /**
     * @throws InvalidArgumentException
     */
    public static function fromBody(Provider $provider, array $body): GithubWebhook
    {
        if (!is_string($body['event'] ?? null)) {
            throw new InvalidArgumentException('Invalid event type for Github webhook');
        }

        return match ($body['event']) {
            'check_run' => GithubCheckRunWebhook::fromBody($provider, $body['payload']),
            default => throw new InvalidArgumentException(
                sprintf(
                    'Invalid event type for Github webhook: %s',
                    $body['event']
                )
            ),
        };
    }
}
