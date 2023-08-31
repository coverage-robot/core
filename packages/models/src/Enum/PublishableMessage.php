<?php

namespace Packages\Models\Enum;

enum PublishableMessage: string
{
    case PullRequest = 'PULL_REQUEST';
    case CheckRun = 'CHECK_RUN';
    case CheckAnnotation = 'CHECK_ANNOTATION';
    case CheckAnnotationCollection = 'CHECK_ANNOTATION_COLLECTION';

    case Collection = 'COLLECTION';
}
