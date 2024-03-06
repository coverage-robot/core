<?php

namespace Packages\Message\PublishableMessage;

use Packages\Event\Model\EventInterface;

interface PublishableCheckRunMessageInterface extends PublishableMessageInterface
{
    public function getEvent(): EventInterface;

    public function getStatus(): PublishableCheckRunStatus;
}