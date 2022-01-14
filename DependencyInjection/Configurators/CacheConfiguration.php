<?php

namespace Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\ChainAdapter;
use Symfony\Component\Cache\Adapter\DoctrineAdapter;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Cache\Marshaller\DefaultMarshaller;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Class CacheConfiguration
 * @package Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators
 *
 * @since 05.05.2021
 */
class CacheConfiguration
{
    /**
     * @param ContainerBuilder $container Container.
     * @param array            $config    Config.
     *
     * @return void
     *
     * @since 27.11.2020
     */
    public function register(array $config, ContainerBuilder $container)
    {
        if (!class_exists(DefaultMarshaller::class)) {
            $container->removeDefinition('cache.default_marshaller');
        }

        if (!$container->hasParameter('container.build_id')) {
            $container->setParameter('container.build_id', 'a fake build id');
        }
        $version = new Parameter('container.build_id');

        if (!ApcuAdapter::isSupported()) {
            $container->removeDefinition('cache.adapter.apcu');
        }

        if (!MemcachedAdapter::isSupported()) {
            $container->removeDefinition('cache.adapter.memcached');
        }

        if (!class_exists(DoctrineAdapter::class)) {
            $container->removeDefinition('cache.adapter.doctrine');
        }

        if (!class_exists(DoctrineDbalAdapter::class)) {
            $container->removeDefinition('cache.adapter.doctrine_dbal');
        }

        if ($container->hasDefinition('cache.adapter.apcu')) {
            $container->getDefinition('cache.adapter.apcu')->replaceArgument(2, $version);
        }

        $container->getDefinition('cache.adapter.system')->replaceArgument(2, $version);
        $container->getDefinition('cache.adapter.filesystem')->replaceArgument(2, $config['directory']);

        if (isset($config['prefix_seed'])) {
            $container->setParameter('cache.prefix.seed', $config['prefix_seed']);
        }
        if ($container->hasParameter('cache.prefix.seed')) {
            // Inline any env vars referenced in the parameter
            $container->setParameter('cache.prefix.seed', $container->resolveEnvPlaceholders($container->getParameter('cache.prefix.seed'), true));
        }
        foreach (['doctrine', 'psr6', 'redis', 'memcached', 'doctrine_dbal', 'pdo'] as $name) {
            if (isset($config[$name = 'default_'.$name.'_provider'])) {
                $container->setAlias('cache.'.$name, new Alias(CachePoolPass::getServiceProvider($container, $config[$name]), false));
            }
        }
        foreach (['app', 'system'] as $name) {
            $config['pools']['cache.'.$name] = [
                'adapters' => [$config[$name]],
                'public' => true,
                'tags' => false,
            ];
        }

        foreach ($config['pools'] as $name => $pool) {
            $pool['adapters'] = $pool['adapters'] ?: ['cache.app'];

            foreach ($pool['adapters'] as $provider => $adapter) {
                if ($config['pools'][$adapter]['tags'] ?? false) {
                    $pool['adapters'][$provider] = $adapter = '.'.$adapter.'.inner';
                }
            }

            if (1 === \count($pool['adapters'])) {
                if (!isset($pool['provider']) && !\is_int($provider)) {
                    $pool['provider'] = $provider;
                }
                $definition = new ChildDefinition($adapter);
            } else {
                $definition = new Definition(ChainAdapter::class, [$pool['adapters'], 0]);
                $pool['reset'] = 'reset';
            }

            if ($pool['tags']) {
                if (true !== $pool['tags'] && ($config['pools'][$pool['tags']]['tags'] ?? false)) {
                    $pool['tags'] = '.'.$pool['tags'].'.inner';
                }
                $container->register($name, TagAwareAdapter::class)
                    ->addArgument(new Reference('.'.$name.'.inner'))
                    ->addArgument(true !== $pool['tags'] ? new Reference($pool['tags']) : null)
                    ->setPublic($pool['public'])
                ;

                $pool['name'] = $name;
                $pool['public'] = false;
                $name = '.'.$name.'.inner';

                if (!\in_array($pool['name'], ['cache.app', 'cache.system'], true)) {
                    $container->registerAliasForArgument($pool['name'], TagAwareCacheInterface::class);
                    $container->registerAliasForArgument($name, CacheInterface::class, $pool['name']);
                    $container->registerAliasForArgument($name, CacheItemPoolInterface::class, $pool['name']);
                }
            } elseif (!\in_array($name, ['cache.app', 'cache.system'], true)) {
                $container->register('.'.$name.'.taggable', TagAwareAdapter::class)
                    ->addArgument(new Reference($name))
                ;
                $container->registerAliasForArgument('.'.$name.'.taggable', TagAwareCacheInterface::class, $name);
                $container->registerAliasForArgument($name, CacheInterface::class);
                $container->registerAliasForArgument($name, CacheItemPoolInterface::class);
            }

            $definition->setPublic($pool['public']);
            unset($pool['adapters'], $pool['public'], $pool['tags']);

            $definition->addTag('cache.pool', $pool);
            $container->setDefinition($name, $definition);
        }

        if (method_exists(PropertyAccessor::class, 'createCache')) {
            $propertyAccessDefinition = $container->register('cache.property_access', AdapterInterface::class);
            $propertyAccessDefinition->setPublic(false);

            if (!$container->getParameter('kernel.debug')) {
                $propertyAccessDefinition->setFactory([PropertyAccessor::class, 'createCache']);
                $propertyAccessDefinition->setArguments([null, 0, $version, new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]);
                $propertyAccessDefinition->addTag('cache.pool', ['clearer' => 'cache.system_clearer']);
                $propertyAccessDefinition->addTag('monolog.logger', ['channel' => 'cache']);
            } else {
                $propertyAccessDefinition->setClass(ArrayAdapter::class);
                $propertyAccessDefinition->setArguments([0, false]);
            }
        }
    }
}