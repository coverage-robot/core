<?php

namespace App\Tests\Service\Publisher;

use Packages\Event\Model\EventInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

abstract class AbstractPublisherServiceTestCase extends KernelTestCase
{
    /**
     * Test that the publisher supports the correct events.
     */
    abstract public function testSupports(EventInterface $event, bool $expectedSupport): void;

    abstract public static function supportsDataProvider(): array;
}
