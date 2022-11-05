<?php
namespace AXS\ApiBundle;

use AXS\ApiBundle\DependencyInjection\AXSApiExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class AXSApiBundle extends AbstractBundle{
    public function configure(DefinitionConfigurator $definition): void{}
    public function setContainer(?ContainerInterface $container = null){}
    public function getPath(): string {
        return \dirname(__DIR__);
    }
    public function getContainerExtension(): ?ExtensionInterface{
        return new AXSApiExtension();
    }
}
