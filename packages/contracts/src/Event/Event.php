<?php

namespace Packages\Contracts\Event;

enum Event: string
{
    /**
     * A new file has been uploaded, and is being processed.
     */
    case INGEST_STARTED = 'INGEST_STARTED';

    /**
     * Ingestion of a new file has occurred successfully.
     */
    case INGEST_SUCCESS = 'INGEST_SUCCESS';

    /**
     * Ingestion of a new file has failed.
     */
    case INGEST_FAILURE = 'INGEST_FAILURE';

    /**
     * All jobs have completed for a given commit, and the results of the uploaded
     * (and carried forward) coverage have been calculated.
     */
    case COVERAGE_FINALISED = 'COVERAGE_FINALISED';

    /**
     * The coverage for a given commit could not be calculated.
     */
    case COVERAGE_FAILED = 'COVERAGE_FAILED';

    /**
     * A job which is being tracked has changed state (i.e. started, completed, queued, etc).
     */
    case JOB_STATE_CHANGE = 'JOB_STATE_CHANGE';

    /**
     * The first of (potentially multiple) uploads has now been seen for a commit.
     */
    case UPLOADS_STARTED = 'UPLOADS_STARTED';

    /**
     * A new coverage file has been uploaded.
     */
    case UPLOAD = 'UPLOAD';

    /**
     * All uploads for a given commit have been processed, and there are no more uploads
     * expected to arrive (i.e. all jobs have also finished).
     */
    case UPLOADS_FINALISED = 'UPLOADS_FINALISED';

    /**
     * The Coverage Robot configuration file has changed in a push to the repository.
     */
    case CONFIGURATION_FILE_CHANGE = 'CONFIGURATION_FILE_CHANGE';
}
