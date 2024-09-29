<?php

namespace Packages\Clients;

use Override;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class ClientsBundle extends AbstractBundle
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
                ->arrayNode('object_reference_store')
                    ->children()
                        ->scalarNode('name')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * Extract any configuration values from the configuration and populate the container with them.
     */
    private function populateContainerWithConfiguration(ContainerConfigurator $container, array $config): void
    {
        $container->parameters()
            ->set('object_reference_store.name', $config['object_reference_store']['name']);
    }
}
