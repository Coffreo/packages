<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\GitLab;

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
        $container->register('packages.plugin.gitlab.adapter', 'Terramar\Packages\Plugin\GitLab\SyncAdapter')
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('router.url_generator'))
            ->addArgument(new Reference('logger.default'));

        $container->getDefinition('packages.helper.sync')
            ->addMethodCall('registerAdapter', [new Reference('packages.plugin.gitlab.adapter')]);

        $container->register('packages.plugin.gitlab.package_subscriber',
            'Terramar\Packages\Plugin\GitLab\PackageSubscriber')
            ->addArgument(new Reference('packages.plugin.gitlab.adapter'))
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addTag('kernel.event_subscriber');

        $container->register('packages.plugin.gitlab.remote_subscriber',
            'Terramar\Packages\Plugin\GitLab\RemoteSubscriber')
            ->addArgument(new Reference('packages.plugin.gitlab.adapter'))
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('logger.default'))
            ->addTag('kernel.event_subscriber');

        $container->register('packages.plugin.gitlab.api_controller', 'Terramar\Packages\Plugin\GitLab\ApiController')
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('logger.default'));

        $container->getDefinition('packages.controller_manager')
            ->addMethodCall('registerController',
                [Actions::REMOTE_NEW, 'Terramar\Packages\Plugin\GitLab\Controller::newAction'])
            ->addMethodCall('registerController',
                [Actions::REMOTE_CREATE, 'Terramar\Packages\Plugin\GitLab\Controller::createAction'])
            ->addMethodCall('registerController',
                [Actions::REMOTE_EDIT, 'Terramar\Packages\Plugin\GitLab\Controller::editAction'])
            ->addMethodCall('registerController',
                [Actions::REMOTE_UPDATE, 'Terramar\Packages\Plugin\GitLab\Controller::updateAction'])
            ->addMethodCall('registerController',
                [Actions::REMOTE_API_GET, 'packages.plugin.gitlab.api_controller:getAction'])
            ->addMethodCall('registerController',
                [Actions::REMOTE_API_UPDATE, 'packages.plugin.gitlab.api_controller:updateAction']);
    }

    /**
     * Get the plugin name.
     *
     * @return string
     */
    public function getName()
    {
        return 'GitLab';
    }

    /**
     */
    public function getVersion()
    {
        return;
    }
}
