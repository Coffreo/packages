<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\DependencyInjection\Compiler;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class OverrideServicesCompilerPass implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        $container->register('packages.api.request_matcher',
            'Symfony\Component\HttpFoundation\RequestMatcher')
            ->addArgument('^/api');

        $container->getDefinition('security.security_subscriber')
            ->setClass('Terramar\Packages\Security\FirewallSubscriber')
            ->addMethodCall('setApiMatcher', [new Reference('packages.api.request_matcher')]);
    }
}
