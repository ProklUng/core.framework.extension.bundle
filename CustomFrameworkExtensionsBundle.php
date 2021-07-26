<?php

namespace Prokl\CustomFrameworkExtensionsBundle;

use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\CompilerPass\ConfigDependencyPass;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\CustomFrameworkExtensionsExtension;
use Symfony\Component\Cache\DependencyInjection\CachePoolClearerPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolPass;
use Symfony\Component\Cache\DependencyInjection\CachePoolPrunerPass;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;

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
        $this->addCompilerPassIfExists($container, MessengerPass::class);
    }

    /**
     * @param ContainerBuilder $container
     * @param string           $class
     * @param string           $type
     * @param integer          $priority
     *
     * @return void
     */
    private function addCompilerPassIfExists(ContainerBuilder $container, string $class, string $type = PassConfig::TYPE_BEFORE_OPTIMIZATION, int $priority = 0)
    {
        $container->addResource(new ClassExistenceResource($class));

        if (class_exists($class)) {
            $container->addCompilerPass(new $class(), $type, $priority);
        }
    }
}
