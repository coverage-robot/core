<?php

namespace Packages\Message\PublishableMessage;

enum PublishableCheckRunStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case SUCCESS = 'success';
    case FAILURE = 'failure';
}
