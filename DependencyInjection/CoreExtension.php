<?php

namespace CommonGateway\CoreBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class CoreExtension extends Extension
{
    /**
     * The basic symfony loaders.
     *
     * Must config array, so unused $configs parameter is allowed as a design decision.
     *
     * @codeCoverageIgnore
     *
     * @param array            $configs   The configuration (un used but required from the extend).
     * @param ContainerBuilder $container The container.
     *
     * @return void This function doesn't return anything.
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yaml');
    }// end load()
}
