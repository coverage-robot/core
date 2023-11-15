<?php

namespace Packages\Telemetry\Enum;

enum Unit: string
{
    case SECONDS = 'Seconds';
    case MICROSECONDS = 'Microseconds';
    case MILLISECONDS = 'Milliseconds';
    case BYTES = 'Bytes';
    case KILOBYTES = 'Kilobytes';
    case MEGABYTES = 'Megabytes';
    case GIGABYTES = 'Gigabytes';
    case TERABYTES = 'Terabytes';
    case BITS = 'Bits';
    case KILOBITS = 'Kilobits';
    case MEGABITS = 'Megabits';
    case GIGABITS = 'Gigabits';
    case TERABITS = 'Terabits';
    case PERCENT = 'Percent';
    case COUNT = 'Count';
    case BYTES_PER_SECOND = 'Bytes/Second';
    case KILOBYTES_PER_SECOND = 'Kilobytes/Second';
    case MEGABYTES_PER_SECOND = 'Megabytes/Second';
    case GIGABYTES_PER_SECOND = 'Gigabytes/Second';
    case TERABYTES_PER_SECOND = 'Terabytes/Second';
    case BITS_PER_SECOND = 'Bits/Second';
    case KILOBITS_PER_SECOND = 'Kilobits/Second';
    case MEGABITS_PER_SECOND = 'Megabits/Second';
    case GIGABITS_PER_SECOND = 'Gigabits/Second';
    case TERABITS_PER_SECOND = 'Terabits/Second';
    case COUNT_PER_SECOND = 'Count/Second';
    case NONE = 'None';
}
