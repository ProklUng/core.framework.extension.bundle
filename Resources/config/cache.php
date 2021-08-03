<?php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerAggregate;

return static function (ContainerConfigurator $container) {

    $container->services()
        ->set('cache_warmer', CacheWarmerAggregate::class)
        ->public()
        ->args([
            tagged_iterator('kernel.cache_warmer'),
            param('kernel.debug'),
            sprintf('%s/%sDeprecations.log', param('kernel.build_dir'), param('kernel.container_class')),
        ])
        ->tag('container.no_preload');
};

