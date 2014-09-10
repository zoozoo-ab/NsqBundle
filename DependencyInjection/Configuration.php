<?php

namespace Socloz\NsqBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * requeue_strategy
 *   [~|true] - set defaults
 *   [false] - disable
 */

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('socloz_nsq');
        $rootNode
            ->children()
                ->arrayNode('connections')
                    ->useAttributeAsKey('name')
                    ->requiresAtLeastOneElement()
                    ->prototype('array')
                        ->children()
                            ->arrayNode('lookupd_hosts')
                                ->defaultValue([])
                                ->treatFalseLike([])
                                ->treatNullLike([])
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('publish_to')
                                ->requiresAtLeastOneElement()
                                ->defaultValue(['127.0.0.1:4150'])
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('requeue_strategy')
                                ->canBeDisabled()
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('max_attempts')->defaultValue(5)->end()
                                    ->arrayNode('delays')
                                        ->defaultValue(array(1000, 5000))
                                        ->prototype('scalar')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('topics')
                    ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('connection')->isRequired()->end()
                            ->scalarNode('retries')->defaultValue(3)->end()
                            ->scalarNode('retry_delay')->defaultValue(100)->end()
                            ->arrayNode('requeue')
                                ->children()
                                    ->scalarNode('connection')->isRequired()->end()
                                    ->scalarNode('topic_name')->isRequired()->defaultValue('__requeue')->end()
                                ->end()
                            ->end()
                            ->arrayNode('consumers')
                                ->useAttributeAsKey('name')
                                ->prototype('scalar')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
