<?php

namespace Prokl\CustomFrameworkExtensionsBundle\DependencyInjection;

use Composer\InstalledVersions;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Cache\Cache;
use Doctrine\DBAL\Connection;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators\DbalConfiguration;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Notifier;
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

        $willBeAvailable = static function (string $package, string $class, string $parentPackage = null) {
            $parentPackages = (array) $parentPackage;
            $parentPackages[] = 'symfony/framework-bundle';

            return static::willBeAvailable($package, $class, $parentPackages);
        };

        $enableIfStandalone = static function (string $package, string $class) use ($willBeAvailable) {
            return !class_exists(FullStack::class) && $willBeAvailable($package, $class) ? 'canBeDisabled' : 'canBeEnabled';
        };

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
        $this->addTwigSection($rootNode);
        $this->addMailerSection($rootNode);
        $this->addMessengerSection($rootNode);
        $this->addNotifierSection($rootNode, $enableIfStandalone);

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
            ->scalarNode('ttl_cache')->defaultValue(3600)->end()
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
     * Annotations.
     *
     * @param ArrayNodeDefinition $rootNode Node.
     *
     * @return void
     */
    private function addTwigSection(ArrayNodeDefinition $rootNode) : void
    {
        $rootNode
            ->children()
            ->arrayNode('twig')
            ->info('Twig configuration')
            ->children()
            ->arrayNode('paths')
            ->scalarPrototype()->end()
            ->end()
            ->booleanNode('cache')->defaultValue(false)->end()
            ->scalarNode('cache_dir')->defaultValue('')->end()
            ->scalarNode('default_path option')->defaultValue('')->end()
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

    private function addMailerSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
            ->arrayNode('mailer')
            ->info('Mailer configuration')
            ->{!class_exists(FullStack::class) && class_exists(Mailer::class) ? 'canBeDisabled' : 'canBeEnabled'}()
            ->validate()
            ->ifTrue(function ($v) { return isset($v['dsn']) && \count($v['transports']); })
            ->thenInvalid('"dsn" and "transports" cannot be used together.')
            ->end()
            ->fixXmlConfig('transport')
            ->children()
            ->scalarNode('dsn')->defaultNull()->end()
            ->scalarNode('dsn_file')->defaultNull()->end()
            ->scalarNode('default_email_from')->defaultNull()->end()
            ->scalarNode('default_email_title')->defaultNull()->end()
            ->arrayNode('transports')
            ->useAttributeAsKey('name')
            ->prototype('scalar')->end()
            ->end()
            ->arrayNode('envelope')
            ->info('Mailer Envelope configuration')
            ->children()
            ->scalarNode('sender')->end()
            ->arrayNode('recipients')
            ->performNoDeepMerging()
            ->beforeNormalization()
            ->ifArray()
            ->then(function ($v) {
                return array_filter(array_values($v));
            })
            ->end()
            ->prototype('scalar')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
        ;
    }

    private function addMessengerSection(ArrayNodeDefinition $rootNode)
    {
        $rootNode
            ->children()
            ->arrayNode('messenger')
            ->info('Messenger configuration')
            ->{!class_exists(FullStack::class) && interface_exists(MessageBusInterface::class) ? 'canBeDisabled' : 'canBeEnabled'}()
            ->fixXmlConfig('transport')
            ->fixXmlConfig('bus', 'buses')
            ->validate()
            ->ifTrue(function ($v) { return isset($v['buses']) && \count($v['buses']) > 1 && null === $v['default_bus']; })
            ->thenInvalid('You must specify the "default_bus" if you define more than one bus.')
            ->end()
            ->validate()
            ->ifTrue(static function ($v): bool { return isset($v['buses']) && null !== $v['default_bus'] && !isset($v['buses'][$v['default_bus']]); })
            ->then(static function (array $v): void { throw new InvalidConfigurationException(sprintf('The specified default bus "%s" is not configured. Available buses are "%s".', $v['default_bus'], implode('", "', array_keys($v['buses'])))); })
            ->end()
            ->children()
            ->arrayNode('routing')
            ->normalizeKeys(false)
            ->useAttributeAsKey('message_class')
            ->beforeNormalization()
            ->always()
            ->then(function ($config) {
                if (!\is_array($config)) {
                    return [];
                }
                // If XML config with only one routing attribute
                if (2 === \count($config) && isset($config['message-class']) && isset($config['sender'])) {
                    $config = [0 => $config];
                }

                $newConfig = [];
                foreach ($config as $k => $v) {
                    if (!\is_int($k)) {
                        $newConfig[$k] = [
                            'senders' => $v['senders'] ?? (\is_array($v) ? array_values($v) : [$v]),
                        ];
                    } else {
                        $newConfig[$v['message-class']]['senders'] = array_map(
                            function ($a) {
                                return \is_string($a) ? $a : $a['service'];
                            },
                            array_values($v['sender'])
                        );
                    }
                }

                return $newConfig;
            })
            ->end()
            ->prototype('array')
            ->performNoDeepMerging()
            ->children()
            ->arrayNode('senders')
            ->requiresAtLeastOneElement()
            ->prototype('scalar')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->arrayNode('serializer')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('default_serializer')
            ->defaultValue('messenger.transport.native_php_serializer')
            ->info('Service id to use as the default serializer for the transports.')
            ->end()
            ->arrayNode('symfony_serializer')
            ->addDefaultsIfNotSet()
            ->children()
            ->scalarNode('format')->defaultValue('json')->info('Serialization format for the messenger.transport.symfony_serializer service (which is not the serializer used by default).')->end()
            ->arrayNode('context')
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->defaultValue([])
            ->info('Context array for the messenger.transport.symfony_serializer service (which is not the serializer used by default).')
            ->prototype('variable')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->arrayNode('transports')
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->beforeNormalization()
            ->ifString()
            ->then(function (string $dsn) {
                return ['dsn' => $dsn];
            })
            ->end()
            ->fixXmlConfig('option')
            ->children()
            ->scalarNode('dsn')->end()
            ->scalarNode('serializer')->defaultNull()->info('Service id of a custom serializer to use.')->end()
            ->arrayNode('options')
            ->normalizeKeys(false)
            ->defaultValue([])
            ->prototype('variable')
            ->end()
            ->end()
            ->arrayNode('retry_strategy')
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
            ->always(function ($v) {
                if (isset($v['service']) && (isset($v['max_retries']) || isset($v['delay']) || isset($v['multiplier']) || isset($v['max_delay']))) {
                    throw new \InvalidArgumentException('The "service" cannot be used along with the other "retry_strategy" options.');
                }

                return $v;
            })
            ->end()
            ->children()
            ->scalarNode('service')->defaultNull()->info('Service id to override the retry strategy entirely')->end()
            ->integerNode('max_retries')->defaultValue(3)->min(0)->end()
            ->integerNode('delay')->defaultValue(1000)->min(0)->info('Time in ms to delay (or the initial value when multiplier is used)')->end()
            ->floatNode('multiplier')->defaultValue(2)->min(1)->info('If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries))')->end()
            ->integerNode('max_delay')->defaultValue(0)->min(0)->info('Max time in ms that a retry should ever be delayed (0 = infinite)')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->scalarNode('failure_transport')
            ->defaultNull()
            ->info('Transport name to send failed messages to (after all retries have failed).')
            ->end()
            ->scalarNode('default_bus')->defaultNull()->end()
            ->arrayNode('buses')
            ->defaultValue(['messenger.bus.default' => ['default_middleware' => true, 'middleware' => []]])
            ->normalizeKeys(false)
            ->useAttributeAsKey('name')
            ->arrayPrototype()
            ->addDefaultsIfNotSet()
            ->children()
            ->enumNode('default_middleware')
            ->values([true, false, 'allow_no_handlers'])
            ->defaultTrue()
            ->end()
            ->arrayNode('middleware')
            ->performNoDeepMerging()
            ->beforeNormalization()
            ->ifTrue(function ($v) { return \is_string($v) || (\is_array($v) && !\is_int(key($v))); })
            ->then(function ($v) { return [$v]; })
            ->end()
            ->defaultValue([])
            ->arrayPrototype()
            ->beforeNormalization()
            ->always()
            ->then(function ($middleware): array {
                if (!\is_array($middleware)) {
                    return ['id' => $middleware];
                }
                if (isset($middleware['id'])) {
                    return $middleware;
                }
                if (1 < \count($middleware)) {
                    throw new \InvalidArgumentException('Invalid middleware at path "framework.messenger": a map with a single factory id as key and its arguments as value was expected, '.json_encode($middleware).' given.');
                }

                return [
                    'id' => key($middleware),
                    'arguments' => current($middleware),
                ];
            })
            ->end()
            ->fixXmlConfig('argument')
            ->children()
            ->scalarNode('id')->isRequired()->cannotBeEmpty()->end()
            ->arrayNode('arguments')
            ->normalizeKeys(false)
            ->defaultValue([])
            ->prototype('variable')
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
        ;
    }

    private function addNotifierSection(ArrayNodeDefinition $rootNode, callable $enableIfStandalone)
    {
        $rootNode
            ->children()
            ->arrayNode('notifier')
            ->info('Notifier configuration')
            ->{$enableIfStandalone('symfony/notifier', Notifier::class)}()
            ->fixXmlConfig('chatter_transport')
            ->children()
            ->arrayNode('chatter_transports')
            ->useAttributeAsKey('name')
            ->prototype('scalar')->end()
            ->end()
            ->end()
            ->fixXmlConfig('texter_transport')
            ->children()
            ->arrayNode('texter_transports')
            ->useAttributeAsKey('name')
            ->prototype('scalar')->end()
            ->end()
            ->end()
            ->children()
            ->booleanNode('notification_on_failed_messages')->defaultFalse()->end()
            ->end()
            ->children()
            ->arrayNode('channel_policy')
            ->useAttributeAsKey('name')
            ->prototype('array')
            ->beforeNormalization()->ifString()->then(function (string $v) { return [$v]; })->end()
            ->prototype('scalar')->end()
            ->end()
            ->end()
            ->end()
            ->fixXmlConfig('admin_recipient')
            ->children()
            ->arrayNode('admin_recipients')
            ->prototype('array')
            ->children()
            ->scalarNode('email')->cannotBeEmpty()->end()
            ->scalarNode('phone')->defaultValue('')->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
            ->end()
        ;
    }

    /**
     * Checks whether a class is available and will remain available in the "no-dev" mode of Composer.
     *
     * When parent packages are provided and if any of them is in dev-only mode,
     * the class will be considered available even if it is also in dev-only mode.
     */
    private static function willBeAvailable(string $package, string $class, array $parentPackages): bool
    {
        if (!class_exists($class) && !interface_exists($class, false) && !trait_exists($class, false)) {
            return false;
        }

        if (!class_exists(InstalledVersions::class) || !InstalledVersions::isInstalled($package) || InstalledVersions::isInstalled($package, false)) {
            return true;
        }

        // the package is installed but in dev-mode only, check if this applies to one of the parent packages too

        $rootPackage = InstalledVersions::getRootPackage()['name'] ?? '';

        if ('symfony/symfony' === $rootPackage) {
            return true;
        }

        foreach ($parentPackages as $parentPackage) {
            if ($rootPackage === $parentPackage || (InstalledVersions::isInstalled($parentPackage) && !InstalledVersions::isInstalled($parentPackage, false))) {
                return true;
            }
        }

        return false;
    }
}