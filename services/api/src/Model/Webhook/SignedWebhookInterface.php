<?php

namespace App\Model\Webhook;

interface SignedWebhookInterface
{
    public const GITHUB_EVENT_HEADER = 'X-GitHub-Event';

    public const SIGNATURE_HEADER = 'x-hub-signature-256';

    public const SIGNATURE_ALGORITHM = 'sha256';

    public function getSignature(): ?string;
}
