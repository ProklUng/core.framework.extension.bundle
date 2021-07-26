<?php

namespace Prokl\CustomFrameworkExtensionsBundle\DependencyInjection;

use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;
use Exception;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators\CacheConfiguration;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators\PropertyInfoConfigurator;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators\SecretConfigurator;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators\SerializerConfigurator;
use Prokl\CustomFrameworkExtensionsBundle\Extra\DoctrineDbalExtension;
use RuntimeException;
use Spiral\Attributes\ReaderInterface;
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransportFactory;
use Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Class CustomFrameworkExtensionsExtension
 * @package Prokl\CustomFrameworkExtensionsBundle\CustomFrameworkExtensions\DependencyInjection
 *
 * @since 05.05.2021
 */
class CustomFrameworkExtensionsExtension extends Extension
{
    private const DIR_CONFIG = '/../Resources/config';

    /**
     * @var boolean $sessionConfigEnabled
     */
    private $sessionConfigEnabled = false;

    /**
     * @var boolean $annotationsConfigEnabled
     */
    private $annotationsConfigEnabled = false;

    /**
     * @var boolean $validatorConfigEnabled
     */
    private $validatorConfigEnabled = false;

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container) : void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // DBAL
        if (!empty($config['dbal']) && $config['dbal']['enabled'] === true) {
            $doctrineDbalExtension = new DoctrineDbalExtension();
            $doctrineDbalExtension->dbalLoad(
                $config['dbal'],
                $container
            );
        }

        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . self::DIR_CONFIG)
        );

        $loaderPhp = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . self::DIR_CONFIG)
        );

        $loader->load('services.yaml');
        $loader->load('commands.yaml');
        $loaderPhp->load('console.php');
        $loader->load('property_extractor.yaml');
        $loader->load('property_info.yaml');
        $loader->load('stuff.yaml');
        $loader->load('filesystem.yaml');
        $loader->load('attributes.yaml');

        if (!empty($config['twig'])) {
            $container->setParameter('twig_config', $config['twig']);
            $container->setParameter('twig_paths', (array)$config['twig']['paths']);
            $container->setParameter('twig_cache_dir', (string)$config['twig']['cache_dir']);
            $container->setParameter('twig_default_path', (string)$config['twig']['default_path option']);
        }

        if (!empty($config['cache']) && $config['cache']['enabled'] === true) {
            $loader->load('cache.yaml');
            $cacheConfig = new CacheConfiguration();
            $cacheConfig->register(
                $config['cache'],
                $container
            );
        }

        if (!empty($config['session']) && $config['session']['enabled'] === true) {
            if (!\extension_loaded('session')) {
                throw new LogicException(
                    'Session support cannot be enabled as the session extension is not installed. See https://php.net/session.installation for instructions.'
                );
            }

            $loader->load('session.yaml');

            $this->sessionConfigEnabled = true;
            $this->registerSessionConfiguration($config['session'], $container);
            if (!empty($config['test'])) {
                $container->getDefinition('test.session.listener')->setArgument(1, '%session.storage.options%');
            }
        }

        if (!empty($config['validation']) && $config['validation']['enabled'] === true) {
            $loader->load('validations.yaml');
            $this->registerValidationConfiguration(
                $config['validation'],
                $container
            );

            $this->validatorConfigEnabled = true;
        }

        if (!empty($config['serializer']) && $config['serializer']['enabled'] === true) {
            $loader->load('serializer.yaml');
            $cacheConfig = new SerializerConfigurator();
            $cacheConfig->register(
                $config['serializer'],
                $container
            );
        }

        if (!empty($config['secrets']) && $config['secrets']['enabled'] === true) {
            $loader->load('secrets.yaml');
            $cacheConfig = new SecretConfigurator();
            $cacheConfig->register(
                $config['secrets'],
                $container
            );

            if (isset($config['secret'])) {
                $container->setParameter('kernel.secret', $config['secret']);
            }
        }

        if (!empty($config['annotations']) && $config['annotations']['enabled'] === true) {
            $container->setParameter('enable_annotations', true);
            $loader->load('annotations.yaml');
            $this->registerAnnotationsConfiguration($config['annotations'], $container);
        }

        $container->setParameter('enable_annotations', $this->annotationsConfigEnabled);

        if (!empty($config['csrf_protection']) && $config['csrf_protection']['enabled'] === true) {
            $loader->load('csrf.yaml');
            $this->registerSecurityCsrfConfiguration($config['csrf_protection'], $container);
        }

        if (!empty($config['property_access']) && $config['property_access']['enabled'] === true) {
            $loader->load('property_accessor.yaml');
            $this->registerPropertyAccessConfiguration(
                $config['property_access'],
                $container
            );
        }

        if (!empty($config['mailer']) && $config['mailer']['enabled'] === true) {
            if (!class_exists(Mailer::class)) {
                throw new LogicException('Mailer support cannot be enabled as the component is not installed. Try running "composer require symfony/mailer".');
            }

            $loader->load('mailer.yaml');
            $loader->load('mailer_transports.yaml');
            $loader->load('mailer_custom.yaml');

            $this->registerMailerConfiguration($config['mailer'], $container);
        }

        if (!empty($config['messenger']) && $config['messenger']['enabled'] === true) {
            if (!interface_exists(MessageBusInterface::class)) {
                throw new LogicException('Messenger support cannot be enabled as the Messenger component is not installed. Try running "composer require symfony/messenger".');
            }

            $loaderPhp->load('messenger.php');

            $this->registerMessengerConfiguration($config['messenger'], $container, (array)$config['validation']);
        } else {
            $container->removeDefinition('console.command.messenger_consume_messages');
            $container->removeDefinition('console.command.messenger_debug');
            $container->removeDefinition('console.command.messenger_stop_workers');
            $container->removeDefinition('console.command.messenger_setup_transports');
            $container->removeDefinition('console.command.messenger_failed_messages_retry');
            $container->removeDefinition('console.command.messenger_failed_messages_show');
            $container->removeDefinition('console.command.messenger_failed_messages_remove');
            $container->removeDefinition('cache.messenger.restart_workers_signal');

            if ($container->hasDefinition('messenger.transport.amqp.factory') && !class_exists(AmqpTransportFactory::class)) {
                if (class_exists(\Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransportFactory::class)) {
                    $container->getDefinition('messenger.transport.amqp.factory')
                        ->setClass(\Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransportFactory::class)
                        ->addTag('messenger.transport_factory');
                } else {
                    $container->removeDefinition('messenger.transport.amqp.factory');
                }
            }

            if ($container->hasDefinition('messenger.transport.redis.factory') && !class_exists(RedisTransportFactory::class)) {
                if (class_exists(\Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory::class)) {
                    $container->getDefinition('messenger.transport.redis.factory')
                        ->setClass(\Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory::class)
                        ->addTag('messenger.transport_factory');
                } else {
                    $container->removeDefinition('messenger.transport.redis.factory');
                }
            }
        }

        $propertyInfo = new PropertyInfoConfigurator();
        $propertyInfo->register($container);

        $this->addAnnotatedClassesToCompile([
            '**\\Controller\\',
            '**\\Entity\\',

            // Added explicitly so that we don't rely on the class map being dumped to make it work
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController',
        ]);


        $container->registerForAutoconfiguration(Command::class)
            ->addTag('console.command');
        $container->registerForAutoconfiguration(EnvVarLoaderInterface::class)
            ->addTag('container.env_var_loader');
        $container->registerForAutoconfiguration(EnvVarProcessorInterface::class)
            ->addTag('container.env_var_processor');
        $container->registerForAutoconfiguration(MessageHandlerInterface::class)
            ->addTag('messenger.message_handler');
    }

    /**
     * @inheritDoc
     */
    public function getAlias() : string
    {
        return 'framework';
    }

    /**
     * @param array            $config    Config.
     * @param ContainerBuilder $container Container.
     *
     * @return void
     */
    private function registerSessionConfiguration(array $config, ContainerBuilder $container) : void
    {
        // session storage
        $container->setAlias('session.storage', $config['storage_id'])->setPublic(false);
        $options = ['cache_limiter' => '0'];
        foreach (['name', 'cookie_lifetime', 'cookie_path', 'cookie_domain', 'cookie_secure', 'cookie_httponly', 'cookie_samesite', 'use_cookies', 'gc_maxlifetime', 'gc_probability', 'gc_divisor', 'sid_length', 'sid_bits_per_character'] as $key) {
            if (isset($config[$key])) {
                $options[$key] = $config[$key];
            }
        }

        if ('auto' === ($options['cookie_secure'] ?? null)) {
            $locator = $container->getDefinition('session_listener')->getArgument(0);
            $locator->setValues($locator->getValues() + [
                    'session_storage' => new Reference('session.storage', ContainerInterface::IGNORE_ON_INVALID_REFERENCE),
                    'request_stack' => new Reference('request_stack'),
                ]);
        }

        $container->setParameter('session.storage.options', $options);

        // session handler (the internal callback registered with PHP session management)
        if (null === $config['handler_id']) {
            // Set the handler class to be null
            $container->getDefinition('session.storage.native')->replaceArgument(1, null);
            $container->getDefinition('session.storage.php_bridge')->replaceArgument(0, null);
            $container->setAlias('session.handler', 'session.handler.native_file')->setPublic(false);
        } else {
            $container->resolveEnvPlaceholders($config['handler_id'], null, $usedEnvs);

            if ($usedEnvs || preg_match('#^[a-z]++://#', $config['handler_id'])) {
                $id = '.cache_connection.'.ContainerBuilder::hash($config['handler_id']);

                $container->getDefinition('session.abstract_handler')
                    ->replaceArgument(0, $container->hasDefinition($id) ? new Reference($id) : $config['handler_id']);

                $container->setAlias('session.handler', 'session.abstract_handler')->setPublic(false);
            } else {
                $container->setAlias('session.handler', $config['handler_id'])->setPublic(false);
            }
        }

        $container->setParameter('session.save_path', $config['save_path']);
        $container->setParameter('session.metadata.update_threshold', $config['metadata_update_threshold']);
    }

    /**
     * @param array            $config    Config.
     * @param ContainerBuilder $container Container.
     *
     * @return void
     */
    private function registerAnnotationsConfiguration(array $config, ContainerBuilder $container) : void
    {
        if (!$config['enabled']) {
            return;
        }

        $this->annotationsConfigEnabled = true;

        if (!class_exists(\Doctrine\Common\Annotations\Annotation::class)) {
            throw new LogicException('Annotations cannot be enabled as the Doctrine Annotation library is not installed.');
        }

        if (!method_exists(AnnotationRegistry::class, 'registerUniqueLoader')) {
            $container->getDefinition('annotations.dummy_registry')
                ->setMethodCalls([['registerLoader', ['class_exists']]]);
        }

        if ('none' !== $config['cache']) {
            if (!class_exists(\Doctrine\Common\Cache\CacheProvider::class)) {
                throw new LogicException('Annotations cannot be enabled as the Doctrine Cache library is not installed.');
            }

            $cacheService = $config['cache'];

            if ('php_array' === $config['cache']) {
                $cacheService = 'annotations.cache';
            } elseif ('file' === $config['cache']) {
                $cacheDir = $container->getParameterBag()->resolveValue($config['file_cache_dir']);
                $cacheTtl = $container->getParameterBag()->resolveValue($config['ttl_cache']);

                if (!is_dir($cacheDir) && false === @mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
                    throw new RuntimeException(sprintf('Could not create cache directory "%s".', $cacheDir));
                }

                $container
                    ->getDefinition('annotations.filesystem_cache_adapter')
                    ->replaceArgument(2, $cacheDir)
                ;

                $container
                    ->getDefinition('annotations.filesystem_cache_adapter')
                    ->replaceArgument(1, $cacheTtl)
                ;

                $cacheService = 'annotations.filesystem_cache';
            }

            $container
                ->getDefinition('annotations.cached_reader')
                ->replaceArgument(2, $config['debug'])
                // temporary property to lazy-reference the cache provider without using it until AddAnnotationsCachedReaderPass runs
                ->setProperty('cacheProviderBackup', new ServiceClosureArgument(new Reference($cacheService)))
                ->addTag('annotations.cached_reader')
            ;

            $container->setAlias('annotation_reader', 'annotations.cached_reader')->setPublic(false);
            $container->setAlias(Reader::class, new Alias('annotations.cached_reader', false));
            $container->setAlias(ReaderInterface::class, new Alias('spiral.psr6_selective_reader', false));
        } else {
            $container->removeDefinition('annotations.cached_reader');
            $container->removeDefinition('spiral.psr6_selective_reader');
            $container->removeDefinition('spiral.attribute_cached_reader');
            $container->removeDefinition('spiral.annotation_cached_reader');

            $container->setAlias(ReaderInterface::class, new Alias('spiral.annotations_selective_reader', false));
        }
    }

    /**
     *
     * @param array            $config    Config.
     * @param ContainerBuilder $container Container.
     *
     * @return void
     *
     * @since 04.04.2021
     */
    private function registerValidationConfiguration(array $config, ContainerBuilder $container) : void
    {
        if (!class_exists(Validation::class)) {
            throw new LogicException(
                'Validation support cannot be enabled as the Validator component is not installed. Try running "composer require symfony/validator".'
            );
        }

        if (!isset($config['email_validation_mode'])) {
            $config['email_validation_mode'] = 'loose';
        }

        $validatorBuilder = $container->getDefinition('validator.builder');

        $definition = $container->findDefinition('validator.email');
        $definition->replaceArgument(0, $config['email_validation_mode']);

        if (\array_key_exists('enable_annotations', $config)) {
            $validatorBuilder->addMethodCall('enableAnnotationMapping', [new Reference('annotation_reader')]);
        }
    }

    /**
     * @param array            $config    Config.
     * @param ContainerBuilder $container Container.
     *
     * @return void
     */
    private function registerSecurityCsrfConfiguration(array $config, ContainerBuilder $container) : void
    {
        if (!class_exists(\Symfony\Component\Security\Csrf\CsrfToken::class)) {
            throw new LogicException('CSRF support cannot be enabled as the Security CSRF component is not installed. Try running "composer require symfony/security-csrf".');
        }

        if (!$this->sessionConfigEnabled) {
            throw new \LogicException('CSRF protection needs sessions to be enabled.');
        }

        if (!class_exists(CsrfExtension::class)) {
            $container->removeDefinition('twig.extension.security_csrf');
        }
    }

    /**
     * @param array            $config    Config.
     * @param ContainerBuilder $container Container.
     *
     * @return void
     */
    private function registerPropertyAccessConfiguration(array $config, ContainerBuilder $container)
    {
        if (!class_exists(PropertyAccessor::class)) {
            return;
        }

        if (version_compare(Kernel::VERSION,'5.2.0', '<')) {
            $container
                ->getDefinition('property_accessor')
                ->replaceArgument(0, $config['magic_call'])
                ->replaceArgument(1, $config['throw_exception_on_invalid_index'])
                ->replaceArgument(3, $config['throw_exception_on_invalid_property_path'])
            ;
        } else {
            $magicMethods = PropertyAccessor::DISALLOW_MAGIC_METHODS;
            $magicMethods |= $config['magic_call'] ? PropertyAccessor::MAGIC_CALL : 0;
            $magicMethods |= $config['magic_get'] ? PropertyAccessor::MAGIC_GET : 0;
            $magicMethods |= $config['magic_set'] ? PropertyAccessor::MAGIC_SET : 0;

            $throw = PropertyAccessor::DO_NOT_THROW;
            $throw |= $config['throw_exception_on_invalid_index'] ? PropertyAccessor::THROW_ON_INVALID_INDEX : 0;
            $throw |= $config['throw_exception_on_invalid_property_path'] ? PropertyAccessor::THROW_ON_INVALID_PROPERTY_PATH : 0;

            $container
                ->getDefinition('property_accessor')
                ->replaceArgument(0, $magicMethods)
                ->replaceArgument(1, $throw)
                ->replaceArgument(3, new Reference(PropertyReadInfoExtractorInterface::class, ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ;
        }
    }

    private function registerMailerConfiguration(array $config, ContainerBuilder $container)
    {
        if (!\count($config['transports']) && null === $config['dsn']) {
            $config['dsn'] = 'smtp://null';
        }
        $transports = $config['dsn'] ? ['main' => $config['dsn']] : $config['transports'];
        $container->getDefinition('mailer.transports')->setArgument(0, $transports);
        $container->getDefinition('mailer.default_transport')->setArgument(0, current($transports));

        $classToServices = [
            SesTransportFactory::class => 'mailer.transport_factory.amazon',
            GmailTransportFactory::class => 'mailer.transport_factory.gmail',
            MandrillTransportFactory::class => 'mailer.transport_factory.mailchimp',
            MailgunTransportFactory::class => 'mailer.transport_factory.mailgun',
            PostmarkTransportFactory::class => 'mailer.transport_factory.postmark',
            SendgridTransportFactory::class => 'mailer.transport_factory.sendgrid',
        ];

        foreach ($classToServices as $class => $service) {
            if (!class_exists($class)) {
                $container->removeDefinition($service);
            }
        }

        $recipients = $config['envelope']['recipients'] ?? null;
        $sender = $config['envelope']['sender'] ?? null;

        $envelopeListener = $container->getDefinition('mailer.envelope_listener');
        $envelopeListener->setArgument(0, $sender);
        $envelopeListener->setArgument(1, $recipients);

        $container->setParameter('mailer_dsn_file', (string)$config['dsn_file']);
        $container->setParameter('mailer_dsn', (string)$config['dsn']);
        $container->setParameter('mailer_default_email_from', (string)$config['default_email_from']);
        $container->setParameter('mailer_default_title', (string)$config['default_email_title']);
    }

    private function registerMessengerConfiguration(array $config, ContainerBuilder $container, array $validationConfig)
    {
        if (ContainerBuilder::willBeAvailable('symfony/amqp-messenger', AmqpTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.amqp.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/redis-messenger', RedisTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.redis.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/amazon-sqs-messenger', AmazonSqsTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.sqs.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/beanstalkd-messenger', BeanstalkdTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.beanstalkd.factory')->addTag('messenger.transport_factory');
        }

        if (null === $config['default_bus'] && 1 === \count($config['buses'])) {
            $config['default_bus'] = key($config['buses']);
        }

        $defaultMiddleware = [
            'before' => [
                ['id' => 'add_bus_name_stamp_middleware'],
                ['id' => 'reject_redelivered_message_middleware'],
                ['id' => 'dispatch_after_current_bus'],
                ['id' => 'failed_message_processing_middleware'],
            ],
            'after' => [
                ['id' => 'send_message'],
                ['id' => 'handle_message'],
            ],
        ];
        foreach ($config['buses'] as $busId => $bus) {
            $middleware = $bus['middleware'];

            if ($bus['default_middleware']) {
                if ('allow_no_handlers' === $bus['default_middleware']) {
                    $defaultMiddleware['after'][1]['arguments'] = [true];
                } else {
                    unset($defaultMiddleware['after'][1]['arguments']);
                }

                // argument to add_bus_name_stamp_middleware
                $defaultMiddleware['before'][0]['arguments'] = [$busId];

                $middleware = array_merge($defaultMiddleware['before'], $middleware, $defaultMiddleware['after']);
            }

            foreach ($middleware as $middlewareItem) {
                if (!$validationConfig['enabled'] && \in_array($middlewareItem['id'], ['validation', 'messenger.middleware.validation'], true)) {
                    throw new LogicException('The Validation middleware is only available when the Validator component is installed and enabled. Try running "composer require symfony/validator".');
                }
            }

            if ($container->getParameter('kernel.debug') && class_exists(Stopwatch::class)) {
                array_unshift($middleware, ['id' => 'traceable', 'arguments' => [$busId]]);
            }

            $container->setParameter($busId.'.middleware', $middleware);
            $container->register($busId, MessageBus::class)->addArgument([])->addTag('messenger.bus');

            if ($busId === $config['default_bus']) {
                $container->setAlias('messenger.default_bus', $busId)->setPublic(true);
                $container->setAlias(MessageBusInterface::class, $busId);
            } else {
                $container->registerAliasForArgument($busId, MessageBusInterface::class);
            }
        }

        if (empty($config['transports'])) {
            $container->removeDefinition('messenger.transport.symfony_serializer');
            $container->removeDefinition('messenger.transport.amqp.factory');
            $container->removeDefinition('messenger.transport.redis.factory');
            $container->removeDefinition('messenger.transport.sqs.factory');
            $container->removeDefinition('messenger.transport.beanstalkd.factory');
            $container->removeAlias(SerializerInterface::class);
        } else {
            $container->getDefinition('messenger.transport.symfony_serializer')
                ->replaceArgument(1, $config['serializer']['symfony_serializer']['format'])
                ->replaceArgument(2, $config['serializer']['symfony_serializer']['context']);
            $container->setAlias('messenger.default_serializer', $config['serializer']['default_serializer']);
        }

        $failureTransports = [];
        if ($config['failure_transport']) {
            if (!isset($config['transports'][$config['failure_transport']])) {
                throw new LogicException(sprintf('Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.', $config['failure_transport']));
            }

            $container->setAlias('messenger.failure_transports.default', 'messenger.transport.'.$config['failure_transport']);
            $failureTransports[] = $config['failure_transport'];
        }

        $failureTransportsByName = [];
        foreach ($config['transports'] as $name => $transport) {
            if ($transport['failure_transport']) {
                $failureTransports[] = $transport['failure_transport'];
                $failureTransportsByName[$name] = $transport['failure_transport'];
            } elseif ($config['failure_transport']) {
                $failureTransportsByName[$name] = $config['failure_transport'];
            }
        }

        $senderAliases = [];
        $transportRetryReferences = [];
        foreach ($config['transports'] as $name => $transport) {
            $serializerId = $transport['serializer'] ?? 'messenger.default_serializer';
            $transportDefinition = (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments([$transport['dsn'], $transport['options'] + ['transport_name' => $name], new Reference($serializerId)])
                ->addTag('messenger.receiver', [
                        'alias' => $name,
                        'is_failure_transport' => \in_array($name, $failureTransports),
                    ]
                )
            ;
            $container->setDefinition($transportId = 'messenger.transport.'.$name, $transportDefinition);
            $senderAliases[$name] = $transportId;

            if (null !== $transport['retry_strategy']['service']) {
                $transportRetryReferences[$name] = new Reference($transport['retry_strategy']['service']);
            } else {
                $retryServiceId = sprintf('messenger.retry.multiplier_retry_strategy.%s', $name);
                $retryDefinition = new ChildDefinition('messenger.retry.abstract_multiplier_retry_strategy');
                $retryDefinition
                    ->replaceArgument(0, $transport['retry_strategy']['max_retries'])
                    ->replaceArgument(1, $transport['retry_strategy']['delay'])
                    ->replaceArgument(2, $transport['retry_strategy']['multiplier'])
                    ->replaceArgument(3, $transport['retry_strategy']['max_delay']);
                $container->setDefinition($retryServiceId, $retryDefinition);

                $transportRetryReferences[$name] = new Reference($retryServiceId);
            }
        }

        $senderReferences = [];
        // alias => service_id
        foreach ($senderAliases as $alias => $serviceId) {
            $senderReferences[$alias] = new Reference($serviceId);
        }
        // service_id => service_id
        foreach ($senderAliases as $serviceId) {
            $senderReferences[$serviceId] = new Reference($serviceId);
        }

        foreach ($config['transports'] as $name => $transport) {
            if ($transport['failure_transport']) {
                if (!isset($senderReferences[$transport['failure_transport']])) {
                    throw new LogicException(sprintf('Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.', $transport['failure_transport']));
                }
            }
        }

        $failureTransportReferencesByTransportName = array_map(function ($failureTransportName) use ($senderReferences) {
            return $senderReferences[$failureTransportName];
        }, $failureTransportsByName);

        $messageToSendersMapping = [];
        foreach ($config['routing'] as $message => $messageConfiguration) {
            if ('*' !== $message && !class_exists($message) && !interface_exists($message, false)) {
                throw new LogicException(sprintf('Invalid Messenger routing configuration: class or interface "%s" not found.', $message));
            }

            // make sure senderAliases contains all senders
            foreach ($messageConfiguration['senders'] as $sender) {
                if (!isset($senderReferences[$sender])) {
                    throw new LogicException(sprintf('Invalid Messenger routing configuration: the "%s" class is being routed to a sender called "%s". This is not a valid transport or service id.', $message, $sender));
                }
            }

            $messageToSendersMapping[$message] = $messageConfiguration['senders'];
        }

        $sendersServiceLocator = ServiceLocatorTagPass::register($container, $senderReferences);

        $container->getDefinition('messenger.senders_locator')
            ->replaceArgument(0, $messageToSendersMapping)
            ->replaceArgument(1, $sendersServiceLocator)
        ;

        $container->getDefinition('messenger.retry.send_failed_message_for_retry_listener')
            ->replaceArgument(0, $sendersServiceLocator)
        ;

        $container->getDefinition('messenger.retry_strategy_locator')
            ->replaceArgument(0, $transportRetryReferences);

        if (\count($failureTransports) > 0) {
            $container->getDefinition('console.command.messenger_failed_messages_retry')
                ->replaceArgument(0, $config['failure_transport']);
            $container->getDefinition('console.command.messenger_failed_messages_show')
                ->replaceArgument(0, $config['failure_transport']);
            $container->getDefinition('console.command.messenger_failed_messages_remove')
                ->replaceArgument(0, $config['failure_transport']);

            $failureTransportsByTransportNameServiceLocator = ServiceLocatorTagPass::register($container, $failureTransportReferencesByTransportName);
            $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')
                ->replaceArgument(0, $failureTransportsByTransportNameServiceLocator);
        } else {
            $container->removeDefinition('messenger.failure.send_failed_message_to_failure_transport_listener');
            $container->removeDefinition('console.command.messenger_failed_messages_retry');
            $container->removeDefinition('console.command.messenger_failed_messages_show');
            $container->removeDefinition('console.command.messenger_failed_messages_remove');
        }
    }
}