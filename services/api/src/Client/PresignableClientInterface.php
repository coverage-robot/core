<?php

namespace App\Client;

use AsyncAws\Core\Input;
use DateTimeImmutable;

/**
 * AsyncAws doesn't provide a segmented interface for services capable of presigning requests,
 * and the services themselves (such as the S3 client) put `final` on the presign method, meaning
 * they cannot be mocked in tests.
 *
 * This allows us to mock the interface and inject it into the signer service.
 */
interface PresignableClientInterface
{
    public function presign(Input $input, ?DateTimeImmutable $expires = null): string;
}
