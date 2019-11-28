<?php

/*
 * Copyright (c) Terramar Labs
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 */

namespace Terramar\Packages\DependencyInjection;

use Nice\DependencyInjection\CompilerAwareExtensionInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class ApiExtension extends Extension implements CompilerAwareExtensionInterface
{
    /**
     * @var array
     */
    private $options = [];

    /**
     * Constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * Loads a specific configuration.
     *
     * @param array $configs An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configs[] = $this->options;
        $configuration = $this->getConfiguration($configs, $container);
        $config = $this->processConfiguration($configuration, $configs);

        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        // ClassMetadataFactory
        $container->register('packages.api.serializer.annotation.reader', 'Doctrine\Common\Annotations\AnnotationReader');
        $container->register('packages.api.serializer.annotation.loader', 'Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader')
            ->addArgument(new Reference('packages.api.serializer.annotation.reader'));
        $container->register('packages.api.serializer.metadata.factory', 'Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory')
            ->addArgument(new Reference('packages.api.serializer.annotation.loader'));

        // Normalizers
        $container->register('packages.api.normalizers.object', 'Symfony\Component\Serializer\Normalizer\ObjectNormalizer')
            ->addArgument(new Reference('packages.api.serializer.metadata.factory'));
        $container->register('packages.api.normalizers.package', 'Terramar\Packages\Serializer\PackageNormalizer')
            ->addArgument(new Reference('router.url_generator'))
            ->addArgument(new Reference('packages.api.normalizers.object'));
        $container->register('packages.api.normalizers.remote', 'Terramar\Packages\Serializer\RemoteNormalizer')
            ->addArgument(new Reference('router.url_generator'))
            ->addArgument(new Reference('packages.api.normalizers.object'));

        // Encoders
        $container->register('packages.api.encoders.json', 'Symfony\Component\Serializer\Encoder\JsonEncoder');

        // Serializer
        $container->register('packages.api.serializer', 'Symfony\Component\Serializer\Serializer')
            ->addArgument([
                new Reference('packages.api.normalizers.package'),
                new Reference('packages.api.normalizers.remote'),
                new Reference('packages.api.normalizers.object')
            ])
            ->addArgument([new Reference('packages.api.encoders.json')]);

        // Controllers
        $container->register('packages.api.package.controller', 'Terramar\Packages\Controller\Api\PackageController')
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('logger.default'))
            ->addArgument(new Reference('packages.api.serializer'))
            ->addArgument(new Reference('packages.helper.plugin'))
            ->addArgument(new Reference('router.url_generator'));

        $container->register('packages.api.remote.controller', 'Terramar\Packages\Controller\Api\RemoteController')
            ->addArgument(new Reference('doctrine.orm.entity_manager'))
            ->addArgument(new Reference('logger.default'))
            ->addArgument(new Reference('packages.api.serializer'))
            ->addArgument(new Reference('packages.helper.plugin'))
            ->addArgument(new Reference('router.url_generator'));
    }

    /**
     * Returns extension configuration.
     *
     * @param array $config An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @return ApiConfiguration
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        return new ApiConfiguration();
    }

    /**
     * Gets the CompilerPasses this extension requires.
     *
     * @return array|CompilerPassInterface[]
     */
    public function getCompilerPasses()
    {
        return [];
    }
}
