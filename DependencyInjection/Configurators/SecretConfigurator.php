<?php

namespace Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\String\LazyString;

/**
 * Class SecretConfigurator
 * @package Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators
 *
 * @since 05.05.2021
 */
class SecretConfigurator
{
    /**
     * Секреты.
     *
     * @param array            $config    Конфиг.
     * @param ContainerBuilder $container Контейнер.
     *
     * @throws InvalidArgumentException
     */
    public function register(array $config, ContainerBuilder $container): void
    {
        $container->getDefinition('secrets.vault')->replaceArgument(0, $config['vault_directory']);

        if ($config['local_dotenv_file']) {
            $container->getDefinition('secrets.local_vault')->replaceArgument(0, $config['local_dotenv_file']);
        } else {
            $container->removeDefinition('secrets.local_vault');
        }

        if ($config['decryption_env_var']) {
            if (!preg_match('/^(?:[-.\w]*+:)*+\w++$/', $config['decryption_env_var'])) {
                throw new InvalidArgumentException(
                    sprintf('Invalid value "%s" set as "decryption_env_var": only "word" characters are allowed.',
                        $config['decryption_env_var'])
                );
            }

            if (class_exists(LazyString::class)) {
                $container->getDefinition('secrets.decryption_key')->replaceArgument(1, $config['decryption_env_var']);
            } else {
                $container->getDefinition('secrets.vault')->replaceArgument(1,
                    "%env({$config['decryption_env_var']})%");
                $container->removeDefinition('secrets.decryption_key');
            }
        } else {
            $container->getDefinition('secrets.vault')->replaceArgument(1, null);
            $container->removeDefinition('secrets.decryption_key');
        }
    }
}