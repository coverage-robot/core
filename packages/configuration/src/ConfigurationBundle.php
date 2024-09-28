<?php

namespace Packages\Configuration;

use Override;
use Packages\Contracts\Environment\Service;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ConfigurationBundle extends AbstractBundle
{
    #[Override]
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder
    ): void {
        $container->import(__DIR__ . '/../config/services.yaml');

        $this->populateContainerWithConfiguration($container, $config);
    }


    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('service')
                    ->values(Service::cases())
                ->end()
            ->end()
        ;
    }

    /**
     * Extract any configuration values from the configuration and populate the container with them.
     */
    private function populateContainerWithConfiguration(ContainerConfigurator $container, array $config): void
    {
        $container->parameters()->set('configuration.service', Service::from($config['service']));
    }
}
