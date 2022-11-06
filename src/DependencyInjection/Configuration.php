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
                    ->scalarNode("api_secret")->end()
                    ->scalarNode("user_entity")->end()
                    ->scalarNode("user_uid")->end()
                    ->integerNode("exp_defer")->end()
                ->end()
            ->end()
        ->end();
        return $tb;
    }
}