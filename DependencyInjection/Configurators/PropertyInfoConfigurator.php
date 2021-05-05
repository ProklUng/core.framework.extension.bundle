<?php

namespace Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;

/**
 * Class PropertyInfoConfigurator
 * @package Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators
 *
 * @since 05.05.2021
 */
class PropertyInfoConfigurator
{
    /**
     * PropertyInfo.
     *
     * @param ContainerBuilder $container Контейнер.
     *
     * @throws LogicException
     */
    public function register(ContainerBuilder $container): void
    {
        if (!interface_exists(PropertyInfoExtractorInterface::class)) {
            throw new LogicException(
                'PropertyInfo support cannot be enabled as the PropertyInfo component is not installed. 
                Try running "composer require symfony/property-info".'
            );
        }

        if (interface_exists('phpDocumentor\Reflection\DocBlockFactoryInterface')) {
            $definition = $container->register(
                'property_info.php_doc_extractor',
                'Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor'
            );
            $definition->setPublic(false);
            $definition->addTag('property_info.description_extractor', ['priority' => -1000]);
            $definition->addTag('property_info.type_extractor', ['priority' => -1001]);
        }
    }
}
