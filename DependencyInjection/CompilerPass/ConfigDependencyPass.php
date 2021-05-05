<?php

namespace Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class ConfigDependencyPass
 * @package Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\CompilerPass
 */
class ConfigDependencyPass implements CompilerPassInterface
{
    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('router')) {
            $container->removeDefinition('console.command.router_match');
        }
    }
}