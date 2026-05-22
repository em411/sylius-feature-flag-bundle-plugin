<?php

namespace Em411\SyliusFeatureFlagPlugin\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('em411_sylius_feature_flag_plugin');

        $treeBuilder->getRootNode()
            ->children()
                ->integerNode('provider_priority')
                    ->info('Priority of the Doctrine provider in the feature flag chain. Higher wins; a value above the code-defined providers (0) lets DB flags override them.')
                    ->defaultValue(100)
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
