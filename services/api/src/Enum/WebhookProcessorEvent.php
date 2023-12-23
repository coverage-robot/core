<?php

namespace App\Enum;

enum WebhookProcessorEvent: string
{
    /**
     * A webhook which indicates that a particular pipeline in the VCS provider
     * has changed state in some fashion.
     *
     * I.e. a new job has started, or a job has completed.
     */
    case JOB_STATE_CHANGE = 'JOB_STATE_CHANGE';

    /**
     * A webhook which indicates that (at least one) commit has been pushed to the repository.
     */
    case COMMITS_PUSHED = 'COMMIT_PUSHED';
}
