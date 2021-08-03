<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Bundle\FrameworkBundle\Command\AboutCommand;
use Symfony\Bundle\FrameworkBundle\Command\AssetsInstallCommand;
use Symfony\Bundle\FrameworkBundle\Command\CachePoolListCommand;
use Symfony\Bundle\FrameworkBundle\Command\CachePoolPruneCommand;
use Symfony\Bundle\FrameworkBundle\Command\ContainerLintCommand;
use Symfony\Bundle\FrameworkBundle\Command\RouterDebugCommand;
use Symfony\Bundle\FrameworkBundle\Command\RouterMatchCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsDecryptToLocalCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsEncryptFromLocalCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsGenerateKeysCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsListCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsRemoveCommand;
use Symfony\Bundle\FrameworkBundle\Command\SecretsSetCommand;
use Prokl\CustomFrameworkExtensionsBundle\Command\Fork\YamlLintCommand;
use Symfony\Bundle\FrameworkBundle\EventListener\SuggestMissingPackageSubscriber;
use Symfony\Component\Console\EventListener\ErrorListener;
use Symfony\Component\Messenger\Command\ConsumeMessagesCommand;
use Symfony\Component\Messenger\Command\DebugCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRemoveCommand;
use Symfony\Component\Messenger\Command\FailedMessagesRetryCommand;
use Symfony\Component\Messenger\Command\FailedMessagesShowCommand;
use Symfony\Component\Messenger\Command\SetupTransportsCommand;
use Symfony\Component\Messenger\Command\StopWorkersCommand;
use Symfony\Component\Translation\Command\XliffLintCommand;
use Symfony\Component\Validator\Command\DebugCommand as ValidatorDebugCommand;

return static function (ContainerConfigurator $container) {

    $container->services()
        ->set('console.error_listener', ErrorListener::class)
        ->args([
            service('logger')->nullOnInvalid(),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('monolog.logger', ['channel' => 'console'])

        ->set('console.suggest_missing_package_subscriber', SuggestMissingPackageSubscriber::class)
        ->tag('kernel.event_subscriber')

        ->set('console.command.about', AboutCommand::class)
        ->tag('console.command')

        ->set('console.command.assets_install', AssetsInstallCommand::class)
        ->args([
            service('filesystem'),
            param('kernel.project_dir'),
        ])
        ->tag('console.command')

        ->set('console.command.cache_pool_list', CachePoolListCommand::class)
        ->args([
            null,
        ])
        ->tag('console.command')

        ->set('console.command.container_lint', ContainerLintCommand::class)
        ->tag('console.command')

        ->set('console.command.messenger_consume_messages', ConsumeMessagesCommand::class)
        ->args([
            abstract_arg('Routable message bus'),
            service('messenger.receiver_locator'),
            service('event_dispatcher'),
            service('logger')->nullOnInvalid(),
            [], // Receiver names
        ])
        ->tag('console.command')
        ->tag('monolog.logger', ['channel' => 'messenger'])

        ->set('console.command.messenger_setup_transports', SetupTransportsCommand::class)
        ->args([
            service('messenger.receiver_locator'),
            [], // Receiver names
        ])
        ->tag('console.command')

        ->set('console.command.messenger_debug', DebugCommand::class)
        ->args([
            [], // Message to handlers mapping
        ])
        ->tag('console.command')

        ->set('console.command.messenger_stop_workers', StopWorkersCommand::class)
        ->args([
            service('cache.messenger.restart_workers_signal'),
        ])
        ->tag('console.command')

        ->set('console.command.messenger_failed_messages_retry', FailedMessagesRetryCommand::class)
        ->args([
            abstract_arg('Default failure receiver name'),
            abstract_arg('Receivers'),
            service('messenger.routable_message_bus'),
            service('event_dispatcher'),
            service('logger'),
        ])
        ->tag('console.command')

        ->set('console.command.messenger_failed_messages_show', FailedMessagesShowCommand::class)
        ->args([
            abstract_arg('Default failure receiver name'),
            abstract_arg('Receivers'),
        ])
        ->tag('console.command')

        ->set('console.command.messenger_failed_messages_remove', FailedMessagesRemoveCommand::class)
        ->args([
            abstract_arg('Default failure receiver name'),
            abstract_arg('Receivers'),
        ])
        ->tag('console.command')

        ->set('console.command.router_debug', RouterDebugCommand::class)
        ->args([
            service('router'),
            service('debug.file_link_formatter')->nullOnInvalid(),
        ])
        ->tag('console.command')

        ->set('console.command.router_match', RouterMatchCommand::class)
        ->args([
            service('router'),
            tagged_iterator('routing.expression_language_provider'),
        ])
        ->tag('console.command')

        ->set('console.command.validator_debug', ValidatorDebugCommand::class)
        ->args([
            service('validator'),
        ])
        ->tag('console.command')

        ->set('console.command.xliff_lint', XliffLintCommand::class)
        ->tag('console.command')

        ->set('console.command.yaml_lint', YamlLintCommand::class)
        ->args([service('kernel')])
        ->tag('console.command')

        ->set('console.command.secrets_set', SecretsSetCommand::class)
        ->args([
            service('secrets.vault'),
            service('secrets.local_vault')->nullOnInvalid(),
        ])
        ->tag('console.command')

        ->set('console.command.secrets_remove', SecretsRemoveCommand::class)
        ->args([
            service('secrets.vault'),
            service('secrets.local_vault')->nullOnInvalid(),
        ])
        ->tag('console.command')

        ->set('console.command.secrets_generate_key', SecretsGenerateKeysCommand::class)
        ->args([
            service('secrets.vault'),
            service('secrets.local_vault')->ignoreOnInvalid(),
        ])
        ->tag('console.command')

        ->set('console.command.secrets_list', SecretsListCommand::class)
        ->args([
            service('secrets.vault'),
            service('secrets.local_vault'),
        ])
        ->tag('console.command')

        ->set('console.command.secrets_decrypt_to_local', SecretsDecryptToLocalCommand::class)
        ->args([
            service('secrets.vault'),
            service('secrets.local_vault')->ignoreOnInvalid(),
        ])
        ->tag('console.command')

        ->set('console.command.secrets_encrypt_from_local', SecretsEncryptFromLocalCommand::class)
        ->args([
            service('secrets.vault'),
            service('secrets.local_vault'),
        ])
        ->tag('console.command')

        ->set('console.command.cache_pool_prune', CachePoolPruneCommand::class)
        ->args([
            [],
        ])
        ->tag('console.command')
    ;
};