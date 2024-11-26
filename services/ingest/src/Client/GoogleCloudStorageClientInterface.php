<?php

declare(strict_types=1);

namespace App\Client;

use Google\Cloud\Storage\Bucket;

interface GoogleCloudStorageClientInterface
{
    /**
     * @return Bucket
     */
    public function bucket(string $name, bool $userProject = false);
}
