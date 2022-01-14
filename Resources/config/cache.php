<?php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\DoctrineAdapter;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;

return static function (ContainerConfigurator $container) {

    $container->services()
        ->set('cache.adapter.filesystem', FilesystemAdapter::class)
        ->abstract()
        ->args([
            '', // namespace
            0, // default lifetime
            sprintf('%s/pools', param('kernel.cache_dir')),
            service('cache.default_marshaller')->ignoreOnInvalid(),
        ])
        ->call('setLogger', [service('logger')->ignoreOnInvalid()])
        ->tag('cache.pool', ['clearer' => 'cache.default_clearer', 'reset' => 'reset'])
        ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.memcached', MemcachedAdapter::class)
        ->abstract()
        ->args([
            abstract_arg('Memcached connection service'),
            '', // namespace
            0, // default lifetime
            service('cache.default_marshaller')->ignoreOnInvalid(),
        ])
        ->call('setLogger', [service('logger')->ignoreOnInvalid()])
        ->tag('cache.pool', [
            'provider' => 'cache.default_memcached_provider',
            'clearer' => 'cache.default_clearer',
            'reset' => 'reset',
        ])
        ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.apcu', ApcuAdapter::class)
        ->abstract()
        ->args([
            '', // namespace
            0, // default lifetime
            abstract_arg('version'),
        ])
        ->call('setLogger', [service('logger')->ignoreOnInvalid()])
        ->tag('cache.pool', ['clearer' => 'cache.default_clearer', 'reset' => 'reset'])
        ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.doctrine', DoctrineAdapter::class)
        ->abstract()
        ->args([
            abstract_arg('Doctrine provider service'),
            '', // namespace
            0, // default lifetime
        ])
        ->call('setLogger', [service('logger')->ignoreOnInvalid()])
        ->tag('cache.pool', [
            'provider' => 'cache.default_doctrine_provider',
            'clearer' => 'cache.default_clearer',
            'reset' => 'reset',
        ])
        ->tag('monolog.logger', ['channel' => 'cache'])
        ->deprecate('symfony/framework-bundle', '5.4', 'The "%service_id%" service inherits from "cache.adapter.doctrine" which is deprecated.')

        ->set('cache.adapter.redis', RedisAdapter::class)
        ->abstract()
        ->args([
            abstract_arg('Redis connection service'),
            '', // namespace
            0, // default lifetime
            service('cache.default_marshaller')->ignoreOnInvalid(),
        ])
        ->call('setLogger', [service('logger')->ignoreOnInvalid()])
        ->tag('cache.pool', [
            'provider' => 'cache.default_redis_provider',
            'clearer' => 'cache.default_clearer',
            'reset' => 'reset',
        ])
        ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache.adapter.redis_tag_aware', RedisTagAwareAdapter::class)
        ->abstract()
        ->args([
            abstract_arg('Redis connection service'),
            '', // namespace
            0, // default lifetime
            service('cache.default_marshaller')->ignoreOnInvalid(),
        ])
        ->call('setLogger', [service('logger')->ignoreOnInvalid()])
        ->tag('cache.pool', [
            'provider' => 'cache.default_redis_provider',
            'clearer' => 'cache.default_clearer',
            'reset' => 'reset',
        ])
        ->tag('monolog.logger', ['channel' => 'cache'])

        ->set('cache_warmer', CacheWarmerAggregate::class)
        ->public()
        ->args([
            tagged_iterator('kernel.cache_warmer'),
            param('kernel.debug'),
            sprintf('%s/%sDeprecations.log', param('kernel.build_dir'), param('kernel.container_class')),
        ])
        ->tag('container.no_preload')

    ->set('cache.adapter.doctrine_dbal', DoctrineDbalAdapter::class)
        ->abstract()
        ->args([
            abstract_arg('DBAL connection service'),
            '', // namespace
            0, // default lifetime
            [], // table options
            service('cache.default_marshaller')->ignoreOnInvalid(),
        ])
        ->call('setLogger', [service('logger')->ignoreOnInvalid()])
        ->tag('cache.pool', [
            'provider' => 'cache.default_doctrine_dbal_provider',
            'clearer' => 'cache.default_clearer',
            'reset' => 'reset',
        ])
        ->tag('monolog.logger', ['channel' => 'cache'])

    ;

};

