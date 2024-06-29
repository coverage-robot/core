<?php

namespace App\Model\Webhook;

interface SignedWebhookInterface
{
    public const string GITHUB_EVENT_HEADER = 'X-GitHub-Event';

    public const string GITHUB_SIGNATURE_HEADER = 'x-hub-signature-256';

    public const string SIGNATURE_ALGORITHM = 'sha256';

    public function getSignature(): ?string;
}
