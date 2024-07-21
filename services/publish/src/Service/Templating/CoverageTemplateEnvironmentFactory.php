<?php

namespace App\Service\Templating;

use App\Extension\CoverageTemplateExtension;
use App\Extension\CoverageTemplateSecurityPolicy;
use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Twig\Environment;
use Twig\Extension\SandboxExtension;
use Twig\Extra\String\StringExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\ContainerRuntimeLoader;

final class CoverageTemplateEnvironmentFactory
{
    public function __construct(
        #[Autowire(value: '%kernel.project_dir%')]
        private string $rootDirectory,
        #[AutowireLocator('app.template_available')]
        private readonly ContainerInterface $container,
        private readonly CoverageTemplateSecurityPolicy $securityPolicy,
        private readonly CoverageTemplateExtension $coverageTemplateExtension
    ) {}

    /**
     * Create an extremely limited templating environment for rendering pull request
     * messages.
     *
     * This environment is sandboxed and has a custom runtime loader in order to separate
     * the templating environment from the rest of the application.
     */
    public function create(): Environment
    {
        $environment = new Environment(
            new FilesystemLoader(
                [
                    'templates/coverage',
                ],
                $this->rootDirectory
            )
        );

        /**
         * Enable the sandbox environment extension so that we can evaluate
         * untrusted template code in the future.
         */
        $environment->addExtension(new SandboxExtension($this->securityPolicy, true));

        /**
         * Allow the runtime loader to load runtime classes from the container.
         */
        $environment->addRuntimeLoader(new ContainerRuntimeLoader($this->container));

        /**
         * Add the coverage template extension to add custom functions for coverage data.
         */
        $environment->addExtension($this->coverageTemplateExtension);

        /**
         * Add simple string helpers provided by Twig. This includes string manipulation
         * using the UnicodeString class - like truncating strings at a certain length.
         */
        $environment->addExtension(new StringExtension());

        return $environment;
    }
}
