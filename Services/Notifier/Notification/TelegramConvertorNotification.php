<?php

namespace Prokl\CustomFrameworkExtensionsBundle\Services\Notifier\Notification;

use League\HTMLToMarkdown\HtmlConverter;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Notification\ChatNotificationInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Class TelegramConvertorNotification
 * @package Local\Notifier
 *
 * @since 31.07.2021
 */
class TelegramConvertorNotification extends Notification implements ChatNotificationInterface
{
    /**
     * @inheritdoc
     */
    public function asChatMessage(RecipientInterface $recipient, string $transport = null): ?ChatMessage
    {
        if ('telegram' === $transport) {
            $content = $this->getSubject() . ' ' . $this->getContent();

            $converter = new HtmlConverter(['remove_nodes' => 'span div']);
            $markdown = $converter->convert($content);

            $telegramOptions = (new TelegramOptions())
                ->parseMode('Markdown')
                ->disableWebPagePreview(true)
                ->disableNotification(false);

            return (new ChatMessage($markdown, $telegramOptions));
        }

        return null;
    }
}