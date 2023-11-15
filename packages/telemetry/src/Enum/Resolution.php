<?php

namespace Packages\Telemetry\Enum;

enum Resolution: int
{
    /**
     * High resolve metrics (1 second)
     */
    case HIGH = 1;

    /**
     * Low (standard) resolution metrics (60 seconds)
     */
    case LOW = 60;
}
