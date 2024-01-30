<?php

namespace Packages\Configuration\Tests\Constant;

use Packages\Configuration\Constant\ConfigurationFile;
use PHPUnit\Framework\TestCase;

final class ConfigurationFileTest extends TestCase
{
    public function testPath(): void
    {
        $this->assertEquals(
            'coveragerobot.yml',
            ConfigurationFile::PATH
        );
    }
}
