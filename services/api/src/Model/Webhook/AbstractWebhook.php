<?php

namespace App\Model\Webhook;

abstract class AbstractWebhook implements WebhookInterface
{
    public function __toString(): string
    {
        return sprintf(
            'Webhook#%s-%s-%s',
            $this->getProvider()->value,
            $this->getOwner(),
            $this->getRepository()
        );
    }

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
