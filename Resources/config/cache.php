<?php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;
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


        ->set('cache_warmer', CacheWarmerAggregate::class)
        ->public()
        ->args([
            tagged_iterator('kernel.cache_warmer'),
            param('kernel.debug'),
            sprintf('%s/%sDeprecations.log', param('kernel.build_dir'), param('kernel.container_class')),
        ])
        ->tag('container.no_preload');
};

