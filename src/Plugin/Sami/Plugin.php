<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\Sami;

use Sami\Sami;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Terramar\Packages\Plugin\Actions;
use Terramar\Packages\Plugin\PluginInterface;

class Plugin implements PluginInterface
{
    /**
     * Configure the given ContainerBuilder.
     *
     * This method allows a plugin to register additional services with the
     * service container.
     *
     * @param ContainerBuilder $container
     */
    public function configure(ContainerBuilder $container)
    {
        $container->register('packages.plugin.sami.subscriber', 'Terramar\Packages\Plugin\Sami\EventSubscriber')
            ->addArgument(new Reference('packages.helper.resque'))
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addTag('kernel.event_subscriber');

        $container->register('packages.plugin.sami.api_controller', 'Terramar\Packages\Plugin\Sami\ApiController')
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('logger.default'));

        $container->getDefinition('packages.controller_manager')
            ->addMethodCall('registerController',
                [Actions::PACKAGE_EDIT, 'Terramar\Packages\Plugin\Sami\Controller::editAction'])
            ->addMethodCall('registerController',
                [Actions::PACKAGE_UPDATE, 'Terramar\Packages\Plugin\Sami\Controller::updateAction'])
            ->addMethodCall('registerController',
                [Actions::PACKAGE_API_GET, 'packages.plugin.sami.api_controller:getAction'])
            ->addMethodCall('registerController',
                [Actions::PACKAGE_API_UPDATE, 'packages.plugin.sami.api_controller:updateAction']);    }

    /**
     * Get the plugin name.
     *
     * @return string
     */
    public function getName()
    {
        return 'Sami';
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return Sami::VERSION;
    }
}
