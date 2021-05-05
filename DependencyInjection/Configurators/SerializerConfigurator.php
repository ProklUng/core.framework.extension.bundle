<?php

namespace Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Normalizer\UnwrappingDenormalizer;
use Symfony\Component\Yaml\Yaml;

/**
 * Class SerializerConfigurator
 * @package Prokl\CustomFrameworkExtensionsBundle\DependencyInjection
 *
 * @since 05.05.2021
 */
class SerializerConfigurator
{
    /**
     * Регистрация сериализатора.
     *
     * @param ContainerBuilder $container Container.
     * @param array            $config    Config.
     *
     * @since 01.11.2020
     *
     * @throws LogicException
     */
    public function register(array $config, ContainerBuilder $container): void
    {
        if (!$this->isConfigEnabled($container, $config)) {
            return;
        }

        $chainLoader = $container->getDefinition('serializer.mapping.chain_loader');

        if (!class_exists(PropertyAccessor::class)) {
            $container->removeAlias('serializer.property_accessor');
            $container->removeDefinition('serializer.normalizer.object');
        }

        if (!class_exists(Yaml::class)) {
            $container->removeDefinition('serializer.encoder.yaml');
        }

        if (!class_exists(UnwrappingDenormalizer::class) || !class_exists(PropertyAccessor::class)) {
            $container->removeDefinition('serializer.denormalizer.unwrapping');
            $container->removeDefinition('serializer.normalizer.denormalizer.unwrapping');
        }

        $serializerLoaders = [];
        // @phpstan-ignore-next-line
        if (isset($config['enable_annotations']) && $config['enable_annotations']) {
            $annotationLoader = new Definition(
                'Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader',
                [new Reference('annotation_reader')]
            );
            $annotationLoader->setPublic(false);

            $serializerLoaders[] = $annotationLoader;
        }

        $chainLoader->replaceArgument(0, $serializerLoaders);
        // @phpstan-ignore-next-line
        if (isset($config['name_converter']) && $config['name_converter']) {
            $container->getDefinition('serializer.name_converter.metadata_aware')->setArgument(
                1,
                new Reference($config['name_converter'])
            );
        }

        if (isset($config['circular_reference_handler']) && $config['circular_reference_handler']) {
            $arguments = $container->getDefinition('serializer.normalizer.object')->getArguments();
            $context = ($arguments[6] ?? []) + ['circular_reference_handler' => new Reference($config['circular_reference_handler'])];
            $container->getDefinition('serializer.normalizer.object')->setArgument(5, null);
            $container->getDefinition('serializer.normalizer.object')->setArgument(6, $context);
        }

        if ($config['max_depth_handler'] ?? false) {
            $defaultContext = $container->getDefinition('serializer.normalizer.object')->getArgument(6);
            $defaultContext += ['max_depth_handler' => new Reference($config['max_depth_handler'])];
            $container->getDefinition('serializer.normalizer.object')->replaceArgument(6, $defaultContext);
        }
    }

    /**
     * @param ContainerBuilder $container Container.
     * @param array            $config    Config.
     *
     * @return boolean Whether the configuration is enabled
     */
    private function isConfigEnabled(ContainerBuilder $container, array $config)
    {
        if (!\array_key_exists('enabled', $config)) {
            throw new InvalidArgumentException("The config array has no 'enabled' key.");
        }

        return (bool) $container->getParameterBag()->resolveValue($config['enabled']);
    }
}
