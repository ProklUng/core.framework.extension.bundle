<?php

namespace Prokl\CustomFrameworkExtensionsBundle\DependencyInjection;

use Composer\InstalledVersions;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\Reader;
use Exception;
use Http\Client\HttpClient;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators\CacheConfiguration;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators\PropertyInfoConfigurator;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators\SecretConfigurator;
use Prokl\CustomFrameworkExtensionsBundle\DependencyInjection\Configurators\SerializerConfigurator;
use Prokl\CustomFrameworkExtensionsBundle\Extra\DoctrineDbalExtension;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerAwareInterface;
use RuntimeException;
use Spiral\Attributes\ReaderInterface;
use Symfony\Bridge\Monolog\Processor\DebugProcessor;
use Symfony\Bridge\Twig\Extension\CsrfExtension;
use Symfony\Bundle\FrameworkBundle\Routing\RouteLoaderInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\EnvVarLoaderInterface;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Lock\Lock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\StoreFactory;
use Symfony\Component\Mailer\Bridge\Google\Transport\GmailTransportFactory;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Mime\MimeTypeGuesserInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\PropertyAccessExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyDescriptionExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyInitializableExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\PropertyInfo\PropertyTypeExtractorInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Transport\AmqpExt\AmqpTransportFactory;
use Symfony\Component\Messenger\Transport\RedisExt\RedisTransportFactory;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Notifier\Bridge\AllMySms\AllMySmsTransportFactory;
use Symfony\Component\Notifier\Bridge\Clickatell\ClickatellTransportFactory;
use Symfony\Component\Notifier\Bridge\Discord\DiscordTransportFactory;
use Symfony\Component\Notifier\Bridge\Esendex\EsendexTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeChat\FakeChatTransportFactory;
use Symfony\Component\Notifier\Bridge\FakeSms\FakeSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\Firebase\FirebaseTransportFactory;
use Symfony\Component\Notifier\Bridge\FreeMobile\FreeMobileTransportFactory;
use Symfony\Component\Notifier\Bridge\GatewayApi\GatewayApiTransportFactory;
use Symfony\Component\Notifier\Bridge\Gitter\GitterTransportFactory;
use Symfony\Component\Notifier\Bridge\GoogleChat\GoogleChatTransportFactory;
use Symfony\Component\Notifier\Bridge\Infobip\InfobipTransportFactory;
use Symfony\Component\Notifier\Bridge\Iqsms\IqsmsTransportFactory;
use Symfony\Component\Notifier\Bridge\LightSms\LightSmsTransportFactory;
use Symfony\Component\Notifier\Bridge\LinkedIn\LinkedInTransportFactory;
use Symfony\Component\Notifier\Bridge\Mattermost\MattermostTransportFactory;
use Symfony\Component\Notifier\Bridge\Mercure\MercureTransportFactory;
use Symfony\Component\Notifier\Bridge\MessageBird\MessageBirdTransport;
use Symfony\Component\Notifier\Bridge\MicrosoftTeams\MicrosoftTeamsTransportFactory;
use Symfony\Component\Notifier\Bridge\Mobyt\MobytTransportFactory;
use Symfony\Component\Notifier\Bridge\Nexmo\NexmoTransportFactory;
use Symfony\Component\Notifier\Bridge\Octopush\OctopushTransportFactory;
use Symfony\Component\Notifier\Bridge\OvhCloud\OvhCloudTransportFactory;
use Symfony\Component\Notifier\Bridge\RocketChat\RocketChatTransportFactory;
use Symfony\Component\Notifier\Bridge\Sendinblue\SendinblueTransportFactory as SendinblueNotifierTransportFactory;
use Symfony\Component\Notifier\Bridge\Sinch\SinchTransportFactory;
use Symfony\Component\Notifier\Bridge\Slack\SlackTransportFactory;
use Symfony\Component\Notifier\Bridge\Smsapi\SmsapiTransportFactory;
use Symfony\Component\Notifier\Bridge\SmsBiuras\SmsBiurasTransportFactory;
use Symfony\Component\Notifier\Bridge\SpotHit\SpotHitTransportFactory;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramTransportFactory;
use Symfony\Component\Notifier\Bridge\Twilio\TwilioTransportFactory;
use Symfony\Component\Notifier\Bridge\Zulip\ZulipTransportFactory;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Contracts\Cache\CallbackInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

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
     * @var boolean $mailerConfigEnabled
     */
    private $mailerConfigEnabled = false;

    /**
     * @var boolean $messengerConfigEnabled
     */
    private $messengerConfigEnabled = false;

    /**
     * @var boolean $httpClientConfigEnabled
     */
    private $httpClientConfigEnabled = false;

    /**
     * @var boolean $notifierConfigEnabled
     */
    private $notifierConfigEnabled = false;

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function load(array $configs, ContainerBuilder $container) : void
    {
        $configuration = new Configuration(
            (bool)$container->getParameter('kernel.debug')
        );

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
        $loaderPhp->load('error_renderer.php');

        if (!empty($config['twig'])) {
            $container->setParameter('twig_config', $config['twig']);
            $container->setParameter('twig_paths', (array)$config['twig']['paths']);
            $container->setParameter('twig_cache_dir', (string)$config['twig']['cache_dir']);
            $container->setParameter('twig_default_path', (string)$config['twig']['default_path option']);
        }

        if (!empty($config['cache']) && $config['cache']['enabled'] === true) {
            $loader->load('cache.yaml');
            $loaderPhp->load('cache.php');

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

        if ($this->httpClientConfigEnabled = $this->isConfigEnabled($container, $config['http_client'])) {
            $this->registerHttpClientConfiguration($config['http_client'], $container, $loaderPhp, $config['profiler']);
        }

        $this->registerProfilerConfiguration($config['profiler'], $container, $loaderPhp);

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

        if (!empty($config['notifier']) && $config['notifier']['enabled'] === true) {
            if (!class_exists(Notifier::class)) {
                throw new LogicException('Notifier support cannot be enabled as the component is not installed. Try running "composer require symfony/notifier".');
            }

            $loaderPhp->load('notifier.php');
            $loaderPhp->load('notifier_transports.php');
            $this->registerNotifierConfiguration($config['notifier'], $container);
        }

        if (!empty($config['lock'])) {
            $loaderPhp->load('lock.php');
            $this->registerLockConfiguration($config['lock'], $container);
        }

        $propertyInfo = new PropertyInfoConfigurator();
        $propertyInfo->register($container);

        if (!$container->hasParameter('debug.file_link_format')) {
            $links = [
                'textmate' => 'txmt://open?url=file://%%f&line=%%l',
                'macvim' => 'mvim://open?url=file://%%f&line=%%l',
                'emacs' => 'emacs://open?url=file://%%f&line=%%l',
                'sublime' => 'subl://open?url=file://%%f&line=%%l',
                'phpstorm' => 'phpstorm://open?file=%%f&line=%%l',
                'atom' => 'atom://core/open/file?filename=%%f&line=%%l',
                'vscode' => 'vscode://file/%%f:%%l',
            ];
            $ide = $config['ide'];
            // mark any env vars found in the ide setting as used
            $container->resolveEnvPlaceholders($ide);

            $container->setParameter('debug.file_link_format', str_replace('%', '%%', ini_get('xdebug.file_link_format') ?: get_cfg_var('xdebug.file_link_format')) ?: ($links[$ide] ?? $ide));
        }

        $this->registerDebugConfiguration($config['php_errors'], $container, $loaderPhp);

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

        $container->registerForAutoconfiguration(CallbackInterface::class)
            ->addTag('container.reversible');
        $container->registerForAutoconfiguration(ServiceSubscriberInterface::class)
            ->addTag('container.service_subscriber');
        $container->registerForAutoconfiguration(DataCollectorInterface::class)
            ->addTag('data_collector');
        $container->registerForAutoconfiguration(CacheClearerInterface::class)
            ->addTag('kernel.cache_clearer');
        $container->registerForAutoconfiguration(CacheWarmerInterface::class)
            ->addTag('kernel.cache_warmer');
        $container->registerForAutoconfiguration(EventDispatcherInterface::class)
            ->addTag('event_dispatcher.dispatcher');

        $container->registerForAutoconfiguration(PropertyListExtractorInterface::class)
            ->addTag('property_info.list_extractor');
        $container->registerForAutoconfiguration(PropertyTypeExtractorInterface::class)
            ->addTag('property_info.type_extractor');
        $container->registerForAutoconfiguration(PropertyDescriptionExtractorInterface::class)
            ->addTag('property_info.description_extractor');
        $container->registerForAutoconfiguration(PropertyAccessExtractorInterface::class)
            ->addTag('property_info.access_extractor');
        $container->registerForAutoconfiguration(PropertyInitializableExtractorInterface::class)
            ->addTag('property_info.initializable_extractor');

        $container->registerForAutoconfiguration(MessageHandlerInterface::class)
            ->addTag('messenger.message_handler');
        $container->registerForAutoconfiguration(TransportFactoryInterface::class)
            ->addTag('messenger.transport_factory');
        $container->registerForAutoconfiguration(MimeTypeGuesserInterface::class)
            ->addTag('mime.mime_type_guesser');

        $container->registerForAutoconfiguration(LoggerAwareInterface::class)
            ->addMethodCall('setLogger', [new Reference('logger')]);

        $container->registerForAutoconfiguration(RouteLoaderInterface::class)
            ->addTag('routing.route_loader');
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

        $container->setParameter('mailer_recipients', (array)$recipients);
        $container->setParameter('mailer_dsn_file', (string)$config['dsn_file']);
        $container->setParameter('mailer_dsn', (string)$config['dsn']);
        $container->setParameter(
            'mailer_default_email_from',
            $config['default_email_from'] ?? (string)$sender
        );
        $container->setParameter('mailer_default_title', (string)$config['default_email_title']);

        $this->mailerConfigEnabled = true;
    }

    private function registerMessengerConfiguration(array $config, ContainerBuilder $container, array $validationConfig)
    {
        // Проблема совместимости с новыми версиями Symfony DI. Т.к. базовая сборка
        // залочена на psr-container 1.0, то с версиями 5.3.x - облом.
        // Пусть пока будет так.
        if (static::willBeAvailable('symfony/amqp-messenger', AmqpTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.amqp.factory')->addTag('messenger.transport_factory');
        }

        if (static::willBeAvailable('symfony/redis-messenger', RedisTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.redis.factory')->addTag('messenger.transport_factory');
        }

        if (static::willBeAvailable('symfony/amazon-sqs-messenger', AmazonSqsTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
            $container->getDefinition('messenger.transport.sqs.factory')->addTag('messenger.transport_factory');
        }

        if (static::willBeAvailable('symfony/beanstalkd-messenger', BeanstalkdTransportFactory::class, ['symfony/framework-bundle', 'symfony/messenger'])) {
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

        $this->messengerConfigEnabled = true;
    }

    private function registerNotifierConfiguration(array $config, ContainerBuilder $container)
    {
        if ($config['chatter_transports']) {
            $container->getDefinition('chatter.transports')->setArgument(0, $config['chatter_transports']);
        } else {
            $container->removeDefinition('chatter');
        }
        if ($config['texter_transports']) {
            $container->getDefinition('texter.transports')->setArgument(0, $config['texter_transports']);
        } else {
            $container->removeDefinition('texter');
        }

        if ($this->mailerConfigEnabled) {
            $sender = $container->getDefinition('mailer.envelope_listener')->getArgument(0);
            $container->getDefinition('notifier.channel.email')->setArgument(2, $sender);
        } else {
            $container->removeDefinition('notifier.channel.email');
        }

        if ($this->messengerConfigEnabled) {
            if ($config['notification_on_failed_messages']) {
                $container->getDefinition('notifier.failed_message_listener')->addTag('kernel.event_subscriber');
            }

            // as we have a bus, the channels don't need the transports
            $container->getDefinition('notifier.channel.chat')->setArgument(0, null);
            if ($container->hasDefinition('notifier.channel.email')) {
                $container->getDefinition('notifier.channel.email')->setArgument(0, null);
            }
            $container->getDefinition('notifier.channel.sms')->setArgument(0, null);
        }

        $container->getDefinition('notifier.channel_policy')->setArgument(0, $config['channel_policy']);

        $classToServices = [
            AllMySmsTransportFactory::class => 'notifier.transport_factory.allmysms',
            ClickatellTransportFactory::class => 'notifier.transport_factory.clickatell',
            DiscordTransportFactory::class => 'notifier.transport_factory.discord',
            EsendexTransportFactory::class => 'notifier.transport_factory.esendex',
            FakeChatTransportFactory::class => 'notifier.transport_factory.fakechat',
            FakeSmsTransportFactory::class => 'notifier.transport_factory.fakesms',
            FirebaseTransportFactory::class => 'notifier.transport_factory.firebase',
            FreeMobileTransportFactory::class => 'notifier.transport_factory.freemobile',
            GatewayApiTransportFactory::class => 'notifier.transport_factory.gatewayapi',
            GitterTransportFactory::class => 'notifier.transport_factory.gitter',
            GoogleChatTransportFactory::class => 'notifier.transport_factory.googlechat',
            InfobipTransportFactory::class => 'notifier.transport_factory.infobip',
            IqsmsTransportFactory::class => 'notifier.transport_factory.iqsms',
            LightSmsTransportFactory::class => 'notifier.transport_factory.lightsms',
            LinkedInTransportFactory::class => 'notifier.transport_factory.linkedin',
            MattermostTransportFactory::class => 'notifier.transport_factory.mattermost',
            MercureTransportFactory::class => 'notifier.transport_factory.mercure',
            MessageBirdTransport::class => 'notifier.transport_factory.messagebird',
            MicrosoftTeamsTransportFactory::class => 'notifier.transport_factory.microsoftteams',
            MobytTransportFactory::class => 'notifier.transport_factory.mobyt',
            NexmoTransportFactory::class => 'notifier.transport_factory.nexmo',
            OctopushTransportFactory::class => 'notifier.transport_factory.octopush',
            OvhCloudTransportFactory::class => 'notifier.transport_factory.ovhcloud',
            RocketChatTransportFactory::class => 'notifier.transport_factory.rocketchat',
            SendinblueNotifierTransportFactory::class => 'notifier.transport_factory.sendinblue',
            SinchTransportFactory::class => 'notifier.transport_factory.sinch',
            SlackTransportFactory::class => 'notifier.transport_factory.slack',
            SmsapiTransportFactory::class => 'notifier.transport_factory.smsapi',
            SmsBiurasTransportFactory::class => 'notifier.transport_factory.smsbiuras',
            SpotHitTransportFactory::class => 'notifier.transport_factory.spothit',
            TelegramTransportFactory::class => 'notifier.transport_factory.telegram',
            TwilioTransportFactory::class => 'notifier.transport_factory.twilio',
            ZulipTransportFactory::class => 'notifier.transport_factory.zulip',
        ];

        $parentPackages = ['symfony/framework-bundle', 'symfony/notifier'];

        foreach ($classToServices as $class => $service) {
            switch ($package = substr($service, \strlen('notifier.transport_factory.'))) {
                case 'fakechat': $package = 'fake-chat'; break;
                case 'fakesms': $package = 'fake-sms'; break;
                case 'freemobile': $package = 'free-mobile'; break;
                case 'googlechat': $package = 'google-chat'; break;
                case 'lightsms': $package = 'light-sms'; break;
                case 'linkedin': $package = 'linked-in'; break;
                case 'messagebird': $package = 'message-bird'; break;
                case 'microsoftteams': $package = 'microsoft-teams'; break;
                case 'ovhcloud': $package = 'ovh-cloud'; break;
                case 'rocketchat': $package = 'rocket-chat'; break;
                case 'smsbiuras': $package = 'sms-biuras'; break;
                case 'spothit': $package = 'spot-hit'; break;
            }

            if (!static::willBeAvailable(sprintf('symfony/%s-notifier', $package), $class, $parentPackages)) {
                $container->removeDefinition($service);
            }
        }

        if (static::willBeAvailable('symfony/mercure-notifier', MercureTransportFactory::class, $parentPackages) && ContainerBuilder::willBeAvailable('symfony/mercure-bundle', MercureBundle::class, $parentPackages)) {
            $container->getDefinition($classToServices[MercureTransportFactory::class])
                ->replaceArgument('$registry', new Reference(HubRegistry::class));
        } elseif (static::willBeAvailable('symfony/mercure-notifier', MercureTransportFactory::class, $parentPackages)) {
            $container->removeDefinition($classToServices[MercureTransportFactory::class]);
        }

        if (static::willBeAvailable('symfony/fake-chat-notifier', FakeChatTransportFactory::class, ['symfony/framework-bundle', 'symfony/notifier', 'symfony/mailer'])) {
            $container->getDefinition($classToServices[FakeChatTransportFactory::class])
                ->replaceArgument('$mailer', new Reference('mailer'));
        }

        if (static::willBeAvailable('symfony/fake-sms-notifier', FakeSmsTransportFactory::class, ['symfony/framework-bundle', 'symfony/notifier', 'symfony/mailer'])) {
            $container->getDefinition($classToServices[FakeSmsTransportFactory::class])
                ->replaceArgument('$mailer', new Reference('mailer'));
        }

        if (isset($config['admin_recipients'])) {
            $notifier = $container->getDefinition('notifier');
            foreach ($config['admin_recipients'] as $i => $recipient) {
                $id = 'notifier.admin_recipient.'.$i;
                $container->setDefinition($id, new Definition(Recipient::class, [$recipient['email'], $recipient['phone']]));
                $notifier->addMethodCall('addAdminRecipient', [new Reference($id)]);
            }
        }

        $this->notifierConfigEnabled = true;
    }

    private function registerLockConfiguration(array $config, ContainerBuilder $container)
    {
        foreach ($config['resources'] as $resourceName => $resourceStores) {
            if (0 === \count($resourceStores)) {
                continue;
            }

            // Generate stores
            $storeDefinitions = [];
            foreach ($resourceStores as $storeDsn) {
                $storeDsn = $container->resolveEnvPlaceholders($storeDsn, null, $usedEnvs);
                $storeDefinition = new Definition(interface_exists(StoreInterface::class) ? StoreInterface::class : PersistingStoreInterface::class);
                $storeDefinition->setFactory([StoreFactory::class, 'createStore']);
                $storeDefinition->setArguments([$storeDsn]);

                $container->setDefinition($storeDefinitionId = '.lock.'.$resourceName.'.store.'.$container->hash($storeDsn), $storeDefinition);

                $storeDefinition = new Reference($storeDefinitionId);

                $storeDefinitions[] = $storeDefinition;
            }

            // Wrap array of stores with CombinedStore
            if (\count($storeDefinitions) > 1) {
                $combinedDefinition = new ChildDefinition('lock.store.combined.abstract');
                $combinedDefinition->replaceArgument(0, $storeDefinitions);
                $container->setDefinition('lock.'.$resourceName.'.store', $combinedDefinition)->setDeprecated('symfony/framework-bundle', '5.2', 'The "%service_id%" service is deprecated, use "lock.'.$resourceName.'.factory" instead.');
                $container->setDefinition($storeDefinitionId = '.lock.'.$resourceName.'.store.'.$container->hash($resourceStores), $combinedDefinition);
            } else {
                $container->setAlias('lock.'.$resourceName.'.store', (new Alias($storeDefinitionId, false))->setDeprecated('symfony/framework-bundle', '5.2', 'The "%alias_id%" alias is deprecated, use "lock.'.$resourceName.'.factory" instead.'));
            }

            // Generate factories for each resource
            $factoryDefinition = new ChildDefinition('lock.factory.abstract');
            $factoryDefinition->replaceArgument(0, new Reference($storeDefinitionId));
            $container->setDefinition('lock.'.$resourceName.'.factory', $factoryDefinition);

            // Generate services for lock instances
            $lockDefinition = new Definition(Lock::class);
            $lockDefinition->setPublic(false);
            $lockDefinition->setFactory([new Reference('lock.'.$resourceName.'.factory'), 'createLock']);
            $lockDefinition->setArguments([$resourceName]);
            $container->setDefinition('lock.'.$resourceName, $lockDefinition)->setDeprecated('symfony/framework-bundle', '5.2', 'The "%service_id%" service is deprecated, use "lock.'.$resourceName.'.factory" instead.');

            // provide alias for default resource
            if ('default' === $resourceName) {
                $container->setAlias('lock.store', (new Alias($storeDefinitionId, false))->setDeprecated('symfony/framework-bundle', '5.2', 'The "%alias_id%" alias is deprecated, use "lock.factory" instead.'));
                $container->setAlias('lock.factory', new Alias('lock.'.$resourceName.'.factory', false));
                $container->setAlias('lock', (new Alias('lock.'.$resourceName, false))->setDeprecated('symfony/framework-bundle', '5.2', 'The "%alias_id%" alias is deprecated, use "lock.factory" instead.'));
                $container->setAlias(PersistingStoreInterface::class, (new Alias($storeDefinitionId, false))->setDeprecated('symfony/framework-bundle', '5.2', 'The "%alias_id%" alias is deprecated, use "'.LockFactory::class.'" instead.'));
                $container->setAlias(LockFactory::class, new Alias('lock.factory', false));
                $container->setAlias(LockInterface::class, (new Alias('lock.'.$resourceName, false))->setDeprecated('symfony/framework-bundle', '5.2', 'The "%alias_id%" alias is deprecated, use "'.LockFactory::class.'" instead.'));
            } else {
                $container->registerAliasForArgument($storeDefinitionId, PersistingStoreInterface::class, $resourceName.'.lock.store')->setDeprecated('symfony/framework-bundle', '5.2', 'The "%alias_id%" alias is deprecated, use "'.LockFactory::class.' '.$resourceName.'LockFactory" instead.');
                $container->registerAliasForArgument('lock.'.$resourceName.'.factory', LockFactory::class, $resourceName.'.lock.factory');
                $container->registerAliasForArgument('lock.'.$resourceName, LockInterface::class, $resourceName.'.lock')->setDeprecated('symfony/framework-bundle', '5.2', 'The "%alias_id%" alias is deprecated, use "'.LockFactory::class.' $'.$resourceName.'LockFactory" instead.');
            }
        }
    }

    /**
     * Checks whether a class is available and will remain available in the "no-dev" mode of Composer.
     *
     * When parent packages are provided and if any of them is in dev-only mode,
     * the class will be considered available even if it is also in dev-only mode.
     */
    private static function willBeAvailable(string $package, string $class, array $parentPackages): bool
    {
        if (method_exists(ContainerBuilder::class, 'willBeAvailable')) {
            return ContainerBuilder::willBeAvailable($package, $class, $parentPackages);
        }

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

    private function registerDebugConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader)
    {
        $loader->load('debug_prod.php');

        if (class_exists(Stopwatch::class)) {
            $container->register('debug.stopwatch', Stopwatch::class)
                ->addArgument(true)
                ->addTag('kernel.reset', ['method' => 'reset']);
            $container->setAlias(Stopwatch::class, new Alias('debug.stopwatch', false));
        }

        $debug = $container->getParameter('kernel.debug');

        if ($debug) {
            $container->setParameter('debug.container.dump', '%kernel.build_dir%/%kernel.container_class%.xml');
        }

        if ($debug && class_exists(Stopwatch::class)) {
            $loader->load('debug.php');
        }

        $definition = $container->findDefinition('debug.debug_handlers_listener');

        if (false === $config['log']) {
            $definition->replaceArgument(1, null);
        } elseif (true !== $config['log']) {
            $definition->replaceArgument(2, $config['log']);
        }

        if (!$config['throw']) {
            $container->setParameter('debug.error_handler.throw_at', 0);
        }

        if ($debug && class_exists(DebugProcessor::class)) {
            $definition = new Definition(DebugProcessor::class);
            $definition->setPublic(false);
            $definition->addArgument(new Reference('request_stack'));
            $container->setDefinition('debug.log_processor', $definition);
        }
    }

    private function registerProfilerConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader)
    {
        if (!$this->isConfigEnabled($container, $config)) {
            // this is needed for the WebProfiler to work even if the profiler is disabled
            $container->setParameter('data_collector.templates', []);

            return;
        }

        $loader->load('profiling.php');
        $loader->load('collectors.php');
        $loader->load('cache_debug.php');

        if ($this->validatorConfigEnabled) {
            $loader->load('validator_debug.php');
        }

        if ($this->messengerConfigEnabled) {
            $loader->load('messenger_debug.php');
        }

        if ($this->mailerConfigEnabled) {
            $loader->load('mailer_debug.php');
        }

//        if ($this->httpClientConfigEnabled) {
//            $loader->load('http_client_debug.php');
//        }
//
//        if ($this->notifierConfigEnabled) {
//            $loader->load('notifier_debug.php');
//        }

        $container->setParameter('profiler_listener.only_exceptions', $config['only_exceptions']);
        $container->setParameter('profiler_listener.only_master_requests', $config['only_master_requests']);

        // Choose storage class based on the DSN
        [$class] = explode(':', $config['dsn'], 2);
        if ('file' !== $class) {
            throw new \LogicException(sprintf('Driver "%s" is not supported for the profiler.', $class));
        }

        $container->setParameter('profiler.storage.dsn', $config['dsn']);

        $container->getDefinition('profiler')
            ->addArgument($config['collect'])
            ->addTag('kernel.reset', ['method' => 'reset']);
    }

    private function registerHttpClientConfiguration(array $config, ContainerBuilder $container, PhpFileLoader $loader, array $profilerConfig)
    {
        $loader->load('http_client.php');

        $options = $config['default_options'] ?? [];
        $retryOptions = $options['retry_failed'] ?? ['enabled' => false];
        unset($options['retry_failed']);
        $container->getDefinition('http_client')->setArguments([$options, $config['max_host_connections'] ?? 6]);

        if (!$hasPsr18 = interface_exists(ClientInterface::class)) {
            $container->removeDefinition('psr18.http_client');
            $container->removeAlias(ClientInterface::class);
        }

        if (!interface_exists(HttpClient::class)) {
            $container->removeDefinition(HttpClient::class);
        }

        if ($this->isConfigEnabled($container, $retryOptions)) {
            $this->registerRetryableHttpClient($retryOptions, 'http_client', $container);
        }

        $httpClientId = ($retryOptions['enabled'] ?? false) ? 'http_client.retryable.inner' : ($this->isConfigEnabled($container, $profilerConfig) ? '.debug.http_client.inner' : 'http_client');
        foreach ($config['scoped_clients'] as $name => $scopeConfig) {
            if ('http_client' === $name) {
                throw new InvalidArgumentException(sprintf('Invalid scope name: "%s" is reserved.', $name));
            }

            $scope = $scopeConfig['scope'] ?? null;
            unset($scopeConfig['scope']);
            $retryOptions = $scopeConfig['retry_failed'] ?? ['enabled' => false];
            unset($scopeConfig['retry_failed']);

            if (null === $scope) {
                $baseUri = $scopeConfig['base_uri'];
                unset($scopeConfig['base_uri']);

                $container->register($name, ScopingHttpClient::class)
                    ->setFactory([ScopingHttpClient::class, 'forBaseUri'])
                    ->setArguments([new Reference($httpClientId), $baseUri, $scopeConfig])
                    ->addTag('http_client.client')
                ;
            } else {
                $container->register($name, ScopingHttpClient::class)
                    ->setArguments([new Reference($httpClientId), [$scope => $scopeConfig], $scope])
                    ->addTag('http_client.client')
                ;
            }

            if ($this->isConfigEnabled($container, $retryOptions)) {
                $this->registerRetryableHttpClient($retryOptions, $name, $container);
            }

            $container->registerAliasForArgument($name, HttpClientInterface::class);

            if ($hasPsr18) {
                $container->setDefinition('psr18.'.$name, new ChildDefinition('psr18.http_client'))
                    ->replaceArgument(0, new Reference($name));

                $container->registerAliasForArgument('psr18.'.$name, ClientInterface::class, $name);
            }
        }

        if ($responseFactoryId = $config['mock_response_factory'] ?? null) {
            $container->register($httpClientId.'.mock_client', MockHttpClient::class)
                ->setDecoratedService($httpClientId, null, -10) // lower priority than TraceableHttpClient
                ->setArguments([new Reference($responseFactoryId)]);
        }
    }

    private function registerRetryableHttpClient(array $options, string $name, ContainerBuilder $container)
    {
        if (!class_exists(RetryableHttpClient::class)) {
            throw new LogicException('Support for retrying failed requests requires symfony/http-client 5.2 or higher, try upgrading.');
        }

        if (null !== $options['retry_strategy']) {
            $retryStrategy = new Reference($options['retry_strategy']);
        } else {
            $retryStrategy = new ChildDefinition('http_client.abstract_retry_strategy');
            $codes = [];
            foreach ($options['http_codes'] as $code => $codeOptions) {
                if ($codeOptions['methods']) {
                    $codes[$code] = $codeOptions['methods'];
                } else {
                    $codes[] = $code;
                }
            }

            $retryStrategy
                ->replaceArgument(0, $codes ?: GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES)
                ->replaceArgument(1, $options['delay'])
                ->replaceArgument(2, $options['multiplier'])
                ->replaceArgument(3, $options['max_delay'])
                ->replaceArgument(4, $options['jitter']);
            $container->setDefinition($name.'.retry_strategy', $retryStrategy);

            $retryStrategy = new Reference($name.'.retry_strategy');
        }

        $container
            ->register($name.'.retryable', RetryableHttpClient::class)
            ->setDecoratedService($name, null, 10) // higher priority than TraceableHttpClient
            ->setArguments([new Reference($name.'.retryable.inner'), $retryStrategy, $options['max_retries'], new Reference('logger')])
            ->addTag('monolog.logger', ['channel' => 'http_client']);
    }
}