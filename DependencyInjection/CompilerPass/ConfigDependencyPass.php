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

        // Если нет Твига, то поудалять кастомные почтовые сервисы.
        if (!$container->hasDefinition('twig.instance')
            &&
            !$container->hasDefinition('twig')
        ) {
            $services = [
                'custom_mail_service',
                'Prokl\CustomFrameworkExtensionsBundle\Services\Mailer\EmailService',
                'Symfony\Bridge\Twig\Mime\BodyRenderer',
                'Symfony\Component\Mime\BodyRendererInterface'
            ];

            foreach ($services as $service) {
                $container->removeDefinition($service);
            }
        }
    }
}