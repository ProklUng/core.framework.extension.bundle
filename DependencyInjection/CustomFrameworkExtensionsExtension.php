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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyInfo\PropertyReadInfoExtractorInterface;
use Symfony\Component\Validator\Validation;

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

        $loader->load('services.yaml');
        $loader->load('commands.yaml');
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

        $propertyInfo = new PropertyInfoConfigurator();
        $propertyInfo->register($container);

        $this->addAnnotatedClassesToCompile([
            '**\\Controller\\',
            '**\\Entity\\',

            // Added explicitly so that we don't rely on the class map being dumped to make it work
            'Symfony\\Bundle\\FrameworkBundle\\Controller\\AbstractController',
        ]);
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
}
