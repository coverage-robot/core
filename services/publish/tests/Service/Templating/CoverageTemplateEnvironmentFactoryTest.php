<?php

namespace App\Tests\Service\Templating;

use App\Extension\CoverageTemplateExtension;
use App\Service\Templating\CoverageTemplateEnvironmentFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Loader\FilesystemLoader;

final class CoverageTemplateEnvironmentFactoryTest extends KernelTestCase
{
    public function testEnvironmentLoadsCorrectDirectoryOfTemplates(): void
    {
        /** @var Environment $environment */
        $environment = $this->getContainer()
            ->get(CoverageTemplateEnvironmentFactory::class)
            ->create();

        $loader = $environment->getLoader();

        $this->assertInstanceOf(FilesystemLoader::class, $loader);

        $paths = $loader->getPaths();

        $this->assertEquals(['templates/coverage'], $paths);
    }

    public function testEnvironmentIsSandboxed(): void
    {
        /** @var Environment $environment */
        $environment = $this->getContainer()
            ->get(CoverageTemplateEnvironmentFactory::class)
            ->create();

        $sandboxExtension = $environment->getExtension(SandboxExtension::class);

        $this->assertTrue($sandboxExtension->isSandboxed());
    }

    public function testEnvironmentHasCoverageExtension(): void
    {
        /** @var Environment $environment */
        $environment = $this->getContainer()
            ->get(CoverageTemplateEnvironmentFactory::class)
            ->create();

        $coverageTemplateExtension = $environment->getExtension(CoverageTemplateExtension::class);

        $this->assertInstanceOf(CoverageTemplateExtension::class, $coverageTemplateExtension);
    }
}