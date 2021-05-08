<?php

namespace Prokl\CustomFrameworkExtensionsBundle\DependencyInjection;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators\DbalConfiguration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\Serializer\Serializer;

/**
 * Class Configuration
 * @package Prokl\CustomFrameworkExtensionsBundle\DependencyInjection
 */
final class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder() : TreeBuilder
    {
        $treeBuilder = new TreeBuilder('framework');
        $rootNode    = $treeBuilder->getRootNode();

        // Валидатор.
        $this->addValidatorSection($rootNode);
        // Кэш.
        $this->addCacheSection($rootNode);
        $this->addSerializerSection($rootNode);
        $this->addSecretsSection($rootNode);
        $this->addAnnotationsSection($rootNode);
        $this->addSessionSection($rootNode);
        $this->addCsrfSection($rootNode);
        $this->addPropertyAccessSection($rootNode);
        $this->addPropertyInfoSection($rootNode);

        $dbalConfig = new DbalConfiguration();
        $dbalConfig->addDbalSection($rootNode);

        return $treeBuilder;
    }

    /**
     * Валидатор.
     *
     * @param ArrayNodeDefinition $arrayNodeDefinition Node.
     *
     * @return void
     */
    private function addValidatorSection(ArrayNodeDefinition $arrayNodeDefinition) : void
    {
        $arrayNodeDefinition
            ->children()
            ->arrayNode('validation')
            ->useAttributeAsKey('name')
            ->prototype('boolean')->end()
            ->prototype('boolean')->end()
            ->defaultValue([
                'enabled' => true,
                'enable_annotations' => true
            ])
            ->end()
            ->end();
    }

    /**
     * Csrf.
     *
     * @param ArrayNodeDefinition $rootNode Node.
     *
     * @return void
     */
    private function addCsrfSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
            ->arrayNode('csrf_protection')
            ->treatFalseLike(['enabled' => false])
            ->treatTrueLike(['enabled' => true])
            ->treatNullLike(['enabled' => true])
            ->addDefaultsIfNotSet()
            ->children()
            // defaults to framework.session.enabled && !class_exists(FullStack::class) && interface_exists(CsrfTokenManagerInterface::class)
            ->booleanNode('enabled')->defaultNull()->end()
            ->end()
            ->end()
            ->end()
        ;
    }

    /**
     * Annotations.
     *
     * @param ArrayNodeDefinition $rootNode Node.
     *
     * @return void
     */
    private function addAnnotationsSection(ArrayNodeDefinition $rootNode) : void
    {
        $rootNode
            ->children()
            ->arrayNode('annotations')
            ->info('annotation configuration')
            ->{class_exists(Annotation::class) ? 'canBeDisabled' : 'canBeEnabled'}()
            ->children()
            ->scalarNode('cache')->defaultValue(interface_exists(Cache::class) ? 'php_array' : 'none')->end()
            ->scalarNode('file_cache_dir')->defaultValue('%kernel.cache_dir%/annotations')->end()
            ->booleanNode('debug')->defaultValue('%kernel.debug%')->end()
            ->end()
            ->end()
            ->end()
        ;
    }

    /**
     * PropertyInfo.
     *
     * @param ArrayNodeDefinition $rootNode Node.
     *
     * @return void
     */
    private function addPropertyAccessSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
            ->arrayNode('property_access')
            ->addDefaultsIfNotSet()
            ->info('Property access configuration')
            ->children()
            ->booleanNode('enabled')->defaultFalse()->end()
            ->booleanNode('magic_call')->defaultFalse()->end()
            ->booleanNode('throw_exception_on_invalid_index')->defaultFalse()->end()
            ->booleanNode('throw_exception_on_invalid_property_path')->defaultTrue()->end()
            ->end()
            ->end()
            ->end()
        ;
    }

    /**
     * @param ArrayNodeDefinition $rootNode Node.
     *
     * @return void
     */
    private function addPropertyInfoSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
            ->arrayNode('property_info')
            ->info('Property info configuration')
            ->{!class_exists(FullStack::class) && interface_exists(PropertyInfoExtractorInterface::class) ? 'canBeDisabled' : 'canBeEnabled'}()
            ->end()
            ->end()
        ;
    }

    /**
     * Serializer.
     *
     * @param ArrayNodeDefinition $arrayNodeDefinition Node.
     *
     * @return void
     */
    private function addSerializerSection(ArrayNodeDefinition $arrayNodeDefinition) : void
    {
        $arrayNodeDefinition
            ->children()
            ->arrayNode('serializer')
            ->info('serializer configuration')
            ->{!class_exists(FullStack::class) && class_exists(Serializer::class) ? 'canBeDisabled' : 'canBeEnabled'}()
            ->children()
            ->booleanNode('enable_annotations')->{!class_exists(FullStack::class) && class_exists(Annotation::class) ? 'defaultTrue' : 'defaultFalse'}()->end()
            ->scalarNode('name_converter')->end()
            ->scalarNode('circular_reference_handler')->end()
            ->scalarNode('max_depth_handler')->end()
            ->arrayNode('mapping')
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('path')
            ->children()
            ->arrayNode('paths')
            ->prototype('scalar')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
        ;
    }

    /**
     * Cache.
     *
     * @param ArrayNodeDefinition $rootNode Node.
     *
     * @return void
     */
    private function addCacheSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
            ->arrayNode('cache')
            ->info('Cache configuration')
            ->addDefaultsIfNotSet()
            ->fixXmlConfig('pool')
            ->children()
            ->booleanNode('enabled')->defaultValue(false)
            ->end()
            ->scalarNode('prefix_seed')
            ->info('Used to namespace cache keys when using several apps with the same shared backend')
            ->example('my-application-name')
            ->end()
            ->scalarNode('app')
            ->info('App related cache pools configuration')
            ->defaultValue('cache.adapter.filesystem')
            ->end()
            ->scalarNode('system')
            ->info('System related cache pools configuration')
            ->defaultValue('cache.adapter.system')
            ->end()
            ->scalarNode('directory')->defaultValue('%kernel.cache_dir%/pools')->end()
            ->scalarNode('default_doctrine_provider')->end()
            ->scalarNode('default_psr6_provider')->end()
            ->scalarNode('default_redis_provider')->defaultValue('redis://localhost')->end()
            ->scalarNode('default_memcached_provider')->defaultValue('memcached://localhost')->end()
            ->scalarNode('default_pdo_provider')->defaultValue(class_exists(Connection::class) ? 'database_connection' : null)->end()
            ->arrayNode('pools')
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->fixXmlConfig('adapter')
            ->beforeNormalization()
            ->ifTrue(function ($v) {
                return (isset($v['adapters']) || \is_array($v['adapter'] ?? null)) && isset($v['provider']);
            })
            ->thenInvalid('Pool cannot have a "provider" while "adapter" is set to a map')
            ->end()
            ->children()
            ->arrayNode('adapters')
            ->performNoDeepMerging()
            ->info('One or more adapters to chain for creating the pool, defaults to "cache.app".')
            ->beforeNormalization()
            ->always()->then(function ($values) {
                if ([0] === array_keys($values) && \is_array($values[0])) {
                    return $values[0];
                }
                $adapters = [];

                foreach ($values as $k => $v) {
                    if (\is_int($k) && \is_string($v)) {
                        $adapters[] = $v;
                    } elseif (!\is_array($v)) {
                        $adapters[$k] = $v;
                    } elseif (isset($v['provider'])) {
                        $adapters[$v['provider']] = $v['name'] ?? $v;
                    } else {
                        $adapters[] = $v['name'] ?? $v;
                    }
                }

                return $adapters;
            })
            ->end()
            ->prototype('scalar')->end()
            ->end()
            ->scalarNode('tags')->defaultNull()->end()
            ->booleanNode('public')->defaultFalse()->end()
            ->integerNode('default_lifetime')->end()
            ->scalarNode('provider')
            ->info('Overwrite the setting from the default provider for this adapter.')
            ->end()
            ->scalarNode('clearer')->end()
            ->end()
            ->end()
            ->validate()
            ->ifTrue(function ($v) {
                return isset($v['cache.app']) || isset($v['cache.system']);
            })
            ->thenInvalid('"cache.app" and "cache.system" are reserved names')
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
        ;
    }

    /**
     * @param ArrayNodeDefinition $rootNode
     *
     * @return void
     */
    private function addSecretsSection(ArrayNodeDefinition $rootNode) : void
    {
        $rootNode
            ->children()
            ->arrayNode('secrets')
            ->canBeDisabled()
            ->children()
            ->scalarNode('vault_directory')->defaultValue('%kernel.project_dir%/config/secrets/%kernel.environment%')->cannotBeEmpty()->end()
            ->scalarNode('local_dotenv_file')->defaultValue('%kernel.project_dir%/.env.%kernel.environment%.local')->end()
            ->scalarNode('decryption_env_var')->defaultValue('base64:default::SYMFONY_DECRYPTION_SECRET')->end()
            ->end()
            ->end()
            ->end()
        ;
    }

    /**
     * @param ArrayNodeDefinition $rootNode Node.
     *
     * @return void
     */
    private function addSessionSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
            ->arrayNode('session')
            ->info('session configuration')
            ->canBeEnabled()
            ->children()
            ->scalarNode('storage_id')->defaultValue('session.storage.native')->end()
            ->scalarNode('handler_id')->defaultValue('session.handler.native_file')->end()
            ->scalarNode('name')
            ->validate()
            ->ifTrue(function ($v) {
                parse_str($v, $parsed);

                return implode('&', array_keys($parsed)) !== (string) $v;
            })
            ->thenInvalid('Session name %s contains illegal character(s)')
            ->end()
            ->end()
            ->scalarNode('cookie_lifetime')->end()
            ->scalarNode('cookie_path')->end()
            ->scalarNode('cookie_domain')->end()
            ->enumNode('cookie_secure')->values([true, false, 'auto'])->end()
            ->booleanNode('cookie_httponly')->defaultTrue()->end()
            ->enumNode('cookie_samesite')->values([null, Cookie::SAMESITE_LAX, Cookie::SAMESITE_STRICT, Cookie::SAMESITE_NONE])->defaultNull()->end()
            ->booleanNode('use_cookies')->end()
            ->scalarNode('gc_divisor')->end()
            ->scalarNode('gc_probability')->defaultValue(1)->end()
            ->scalarNode('gc_maxlifetime')->end()
            ->scalarNode('save_path')->defaultValue('%kernel.cache_dir%/sessions')->end()
            ->integerNode('metadata_update_threshold')
            ->defaultValue(0)
            ->info('seconds to wait between 2 session metadata updates')
            ->end()
            ->integerNode('sid_length')
            ->min(22)
            ->max(256)
            ->end()
            ->integerNode('sid_bits_per_character')
            ->min(4)
            ->max(6)
            ->end()
            ->end()
            ->end()
            ->end()
        ;
    }
}
