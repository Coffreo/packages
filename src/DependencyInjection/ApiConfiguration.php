<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ApiConfiguration implements ConfigurationInterface
{
    /**
     * Generates the configuration tree builder.
     *
     * @return \Symfony\Component\Config\Definition\Builder\TreeBuilder The tree builder
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('api')
            ->canBeEnabled()
            ->validate()
                ->ifTrue(function($config) {
                    return $config['enabled'] && !$config['token'];
                })
                ->then(function($config) {
                    trigger_error('Disabling api (no token supplied)', E_USER_WARNING);
                    return array_merge($config, ['enabled' => false]);
                })
            ->end();

        $rootNode
            ->children()
                ->booleanNode('enabled')->defaultFalse()->end()
                ->scalarNode('token')->defaultFalse()->end()
            ->end();

        return $treeBuilder;
    }
}
