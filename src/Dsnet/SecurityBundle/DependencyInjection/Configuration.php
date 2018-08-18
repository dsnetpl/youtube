<?php

namespace Dsnet\SecurityBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $treeBuilder->root('dsnet_security', 'array')
            ->children()
            ->variableNode('client_id')
            ->cannotBeEmpty()
            ->isRequired()
            ->end()
            ->variableNode('client_secret')
            ->cannotBeEmpty()
            ->isRequired()
            ->end()
            ->variableNode('redirect_uri')
            ->cannotBeEmpty()
            ->isRequired()
            ->end()
            ->variableNode('panel_base_url')
            ->cannotBeEmpty()
            ->isRequired()
            ->end()
        ;
        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.
        return $treeBuilder;
    }
}
