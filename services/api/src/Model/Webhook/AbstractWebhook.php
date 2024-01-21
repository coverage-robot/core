<?php

namespace App\Model\Webhook;

use Override;

abstract class AbstractWebhook implements WebhookInterface
{
    #[Override]
    public function __toString(): string
    {
        return sprintf(
            'Webhook#%s-%s-%s',
            $this->getProvider()->value,
            $this->getOwner(),
            $this->getRepository()
        );
    }

    #[Override]
    public function getMessageGroup(): string
    {
        return md5(
            implode(
                '',
                [
                    $this->getProvider()->value,
                    $this->getOwner(),
                    $this->getRepository()
                ]
            )
        );
    }
}
