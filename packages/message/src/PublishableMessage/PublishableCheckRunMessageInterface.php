<?php

declare(strict_types=1);

namespace Packages\Message\PublishableMessage;

use Override;
use Packages\Event\Model\EventInterface;

interface PublishableCheckRunMessageInterface extends PublishableMessageInterface
{
    #[Override]
    public function getEvent(): EventInterface;

    public function getStatus(): PublishableCheckRunStatus;
}
