<?php

namespace Prokl\CustomFrameworkExtensionsBundle;

use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\CompilerPass\ConfigDependencyPass;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\CustomFrameworkExtensionsExtension;
use Symfony\Component\Cache\DependencyInjection\CachePoolClearerPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolPrunerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class CustomFrameworkExtensionsBundle
 * @package Prokl\CustomFrameworkExtensionsBundle
 *
 * @since 05.05.2021
 */
class CustomFrameworkExtensionsBundle extends Bundle
{
   /**
   * @inheritDoc
   */
    public function getContainerExtension()
    {
        if ($this->extension === null) {
            $this->extension = new CustomFrameworkExtensionsExtension();
        }

        return $this->extension;
    }

    /**
     * @inheritDoc
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new ConfigDependencyPass());

        $container->addCompilerPass(new CachePoolPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 32);
        $container->addCompilerPass(new CachePoolClearerPass(), PassConfig::TYPE_AFTER_REMOVING);
        $container->addCompilerPass(new CachePoolPrunerPass(), PassConfig::TYPE_AFTER_REMOVING);
    }
}
