<?php

declare(strict_types=1);

namespace App\Enum;

enum EnvironmentVariable: string
{
    case WEBHOOK_SECRET = 'WEBHOOK_SECRET';
    case PROJECT_POOL_ID = 'PROJECT_POOL_ID';
    case PROJECT_POOL_CLIENT_ID = 'PROJECT_POOL_CLIENT_ID';
    case PROJECT_POOL_CLIENT_SECRET = 'PROJECT_POOL_CLIENT_SECRET';
    case REF_METADATA_TABLE = 'REF_METADATA_TABLE';
}
