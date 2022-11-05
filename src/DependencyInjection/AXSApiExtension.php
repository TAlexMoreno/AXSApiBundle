<?php
namespace AXS\ApiBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class AXSApiExtension extends Extension {
    public function load(array $configs, ContainerBuilder $container){
        $configuration = new Configuration();
        
        $config = $this->processConfiguration($configuration, $configs);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__."/../../Resources/config"));
        $loader->load("services.yaml");

        $definition = $container->getDefinition("AXS\ApiBundle\Controller\Api");
        $definition->setArgument('$user_entity', $config["security"]["user_entity"]);
        $definition->setArgument('$user_uid', $config["security"]["user_uid"]);
    }
}