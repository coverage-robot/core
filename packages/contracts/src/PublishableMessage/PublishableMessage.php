<?php

namespace Packages\Contracts\PublishableMessage;

enum PublishableMessage: string
{
    case PULL_REQUEST = 'PULL_REQUEST';
    case CHECK_RUN = 'CHECK_RUN';
    case MISSING_COVERAGE_ANNOTATION = 'MISSING_COVERAGE_ANNOTATION';
    case PARTIAL_BRANCH_ANNOTATION = 'PARTIAL_BRANCH_ANNOTATION';
    case COLLECTION = 'COLLECTION';
}
