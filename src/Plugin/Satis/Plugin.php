<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\Plugin\Satis;

use Composer\Satis\Satis;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Terramar\Packages\Plugin\Actions;
use Terramar\Packages\Plugin\CompilerAwarePluginInterface;
use Terramar\Packages\Plugin\PluginInterface;
use Terramar\Packages\Plugin\RouterPluginInterface;
use Terramar\Packages\Router\RouteCollector;

class Plugin implements PluginInterface, RouterPluginInterface, CompilerAwarePluginInterface
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
        $container->register('packages.plugin.satis.subscriber', 'Terramar\Packages\Plugin\Satis\EventSubscriber')
            ->addArgument(new Reference('packages.helper.resque'))
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addTag('kernel.event_subscriber');

        $container->register('packages.plugin.satis.config_helper',
            'Terramar\Packages\Plugin\Satis\ConfigurationHelper')
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('router.url_generator'))
            ->addArgument('%app.root_dir%')
            ->addArgument('%app.cache_dir%')
            ->addArgument('%packages.configuration%');

        $container->register('packages.plugin.satis.frontend_controller', 'Terramar\Packages\Plugin\Satis\FrontendController')
            ->addArgument('%packages.configuration%')
            ->addArgument(new Reference('security.authenticator'));

        $container->register('packages.plugin.satis.inventory_controller', 'Terramar\Packages\Plugin\Satis\InventoryController')
            ->addMethodCall('setContainer', [new Reference('service_container')]);

        $container->register('packages.plugin.satis.api_controller', 'Terramar\Packages\Plugin\Satis\ApiController')
            ->addArgument(new Reference('app'))
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('logger.default'));

        $container->getDefinition('packages.controller_manager')
            ->addMethodCall('registerController',
                [Actions::PACKAGE_EDIT, 'Terramar\Packages\Plugin\Satis\Controller::editAction'])
            ->addMethodCall('registerController',
                [Actions::PACKAGE_UPDATE, 'Terramar\Packages\Plugin\Satis\Controller::updateAction'])
            ->addMethodCall('registerController',
                [Actions::PACKAGE_API_GET, 'packages.plugin.satis.api_controller:getAction'])
            ->addMethodCall('registerController',
                [Actions::PACKAGE_API_UPDATE, 'packages.plugin.satis.api_controller:updateAction']);

        $container->getDefinition('packages.command_registry')
            ->addMethodCall('addCommand', ['Terramar\Packages\Plugin\Satis\Command\BuildCommand'])
            ->addMethodCall('addCommand', ['Terramar\Packages\Plugin\Satis\Command\UpdateCommand']);
    }

    /**
     * Configure the given RouteCollector.
     *
     * This method allows a plugin to register additional HTTP routes with the
     * RouteCollector.
     *
     * @param RouteCollector $collector
     * @return void
     */
    public function collect(RouteCollector $collector)
    {
        $collector->map('/packages.json', 'satis_packages', 'packages.plugin.satis.frontend_controller:outputAction');
        $collector->map('/include/{file}', null, 'packages.plugin.satis.frontend_controller:outputAction');
        $collector->map('/dist/{group}/{package}/{file}', null, 'packages.plugin.satis.frontend_controller:distAction');

        $collector->map('/packages', 'packages_index',
            'packages.plugin.satis.inventory_controller:indexAction');
        $collector->map('/packages/{id}', 'packages_view',
            'packages.plugin.satis.inventory_controller:viewAction');
        $collector->map('/packages/{id}/{version}', 'packages_view_version',
            'packages.plugin.satis.inventory_controller:viewAction');
    }

    /**
     * Get the plugin name.
     *
     * @return string
     */
    public function getName()
    {
        return 'Satis';
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return Satis::VERSION;
    }

    /**
     * Gets the CompilerPasses this plugin requires.
     *
     * @return array|CompilerPassInterface[]
     */
    public function getCompilerPasses()
    {
        return array(new FirewallCompilerPass());
    }
}
