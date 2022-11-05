<?php
namespace AXS\ApiBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface {
    public function getConfigTreeBuilder(){
        $tb = new TreeBuilder("axs_api");
        $tb->getRootNode()->children()
            ->arrayNode("security")
                ->children()
                    ->scalarNode("user_entity")->end()
                    ->scalarNode("user_uid")->end()
                ->end()
            ->end()
        ->end();
        return $tb;
    }
}