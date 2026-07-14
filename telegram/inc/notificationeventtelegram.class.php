<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Handles bulk Telegram notifications from GLPI notification system.
 */
class PluginTelegramNotificationEventTelegram extends NotificationEventAbstract implements NotificationEventInterface
{
    public static function getTargetFieldName()
    {
        return 'telegram_id';
    }

    public static function getTargetField(&$data)
    {
        $field = self::getTargetFieldName();

        if (isset($data['users_id'])) {
            $user = new User();
            if ($user->getFromDB($data['users_id'])) {
                $data[$field] = $user->fields['telegram_id'] ?? '';
                $data['telegram_chat_id'] = $user->fields['telegram_chat_id'] ?? '';
                $data['telegram_thread_id'] = $user->fields['telegram_thread_id'] ?? null;
                
                if (empty($data[$field]) && !empty($data['telegram_chat_id'])) {
                    $data[$field] = $data['telegram_chat_id'];
                }
            }
        }

        if (!isset($data[$field])) {
            $data[$field] = '';
        }
        if (!isset($data['telegram_chat_id'])) {
            $data['telegram_chat_id'] = '';
        }
        if (!isset($data['telegram_thread_id'])) {
            $data['telegram_thread_id'] = null;
        }

        return $field;
    }

    public static function canCron()
    {
        return true;
    }

    public static function isHtml()
    {
        return true;
    }

    public static function getAdminData()
    {
        return ['telegram_id' => ''];
    }

    public static function getEntityAdminsData($entity)
    {
        return [];
    }

    public static function send(array $data)
    {
        $config = GlpiPlugin\Telegram\Config::getConfig();
        if (empty($config['bot_token'])) {
            PluginTelegramDebugLogger::log("Bulk send skipped: bot token not configured");
            return false;
        }

        $sender = new PluginTelegramSender($config);
        $sent = false;
        $hasNotifications = false;

        foreach ($data as $index => $notification) {
            $addresses = self::getAddressesForRecipient($notification);
            if (empty($addresses)) {
                continue;
            }

            if (!$hasNotifications) {
                $hasNotifications = true;
                PluginTelegramDebugLogger::logSeparator();
                PluginTelegramDebugLogger::log("=== BULK NOTIFICATIONS | " . count($data) . " items ===");
            }

            PluginTelegramDebugLogger::log("--- Notification #{$index} ---");
            PluginTelegramDebugLogger::log("Recipient users_id: " . ($notification['users_id'] ?? 'N/A'));

            $message = '';
            $subject = $notification['name'] ?? $notification['subject'] ?? '';

            if (!empty($notification['body_html'])) {
                $message = PluginTelegramHtmlToTelegramHtml::convert($notification['body_html']);
            } elseif (!empty($notification['content_html'])) {
                $message = PluginTelegramHtmlToTelegramHtml::convert($notification['content_html']);
            } elseif (!empty($notification['body_text'])) {
                $message = $notification['body_text'];
            } elseif (!empty($notification['content_text'])) {
                $message = $notification['content_text'];
            }

            if (empty($message)) {
                PluginTelegramDebugLogger::log("Empty message body, skipping");
                continue;
            }

            // Add subject if present (as bold)
            if (!empty($subject)) {
                $subject = trim($subject);
                $message = "<b>" . $subject . "</b>\n\n" . $message;
            }

            // Trim and limit message length
            $message = trim($message);
            if (mb_strlen($message) > 4000) {
                $message = mb_substr($message, 0, 3997) . '...';
            }

            if (PluginTelegramDebugLogger::isEnabled()) {
                PluginTelegramDebugLogger::log("Message preview: " . substr($message, 0, 200) . (strlen($message) > 200 ? '...' : ''));
            }

            foreach ($addresses as $addr) {
                try {
                    PluginTelegramDebugLogger::log("Sending to: {$addr['address']}" . ($addr['thread_id'] ? " (thread: {$addr['thread_id']})" : ""));
                    if ($sender->sendMessage($addr['address'], $message, $addr['thread_id'])) {
                        $sent = true;
                    }
                } catch (\Exception $e) {
                    PluginTelegramDebugLogger::log("Failed: {$addr['address']} - " . $e->getMessage());
                }
            }
        }

        if ($hasNotifications) {
            PluginTelegramDebugLogger::log("Bulk result: " . ($sent ? "SUCCESS (at least one sent)" : "FAILED (none sent)"));
            PluginTelegramDebugLogger::logSeparator();
        }

        return $sent;
    }

    private static function getAddressesForRecipient($notification)
    {
        $addresses = [];

        if (!empty($notification['users_id'])) {
            $user = new User();
            if ($user->getFromDB($notification['users_id'])) {
                $telegramId = $user->fields['telegram_id'] ?? '';
                $chatId = $user->fields['telegram_chat_id'] ?? '';
                $threadId = $user->fields['telegram_thread_id'] ?? null;
                
                PluginTelegramDebugLogger::log("User found:", [
                    'id' => $user->fields['id'],
                    'name' => $user->fields['name'] ?? 'unknown',
                    'telegram_id' => $telegramId ?: 'EMPTY',
                    'telegram_chat_id' => $chatId ?: 'EMPTY',
                    'telegram_thread_id' => $threadId ?: 'NONE'
                ]);

                if (!empty($telegramId)) {
                    $addresses[] = ['address' => $telegramId, 'thread_id' => null];
                }
                
                if (!empty($chatId)) {
                    $addresses[] = ['address' => $chatId, 'thread_id' => $threadId];
                }

                if (empty($addresses)) {
                    PluginTelegramDebugLogger::log("User has no Telegram addresses configured");
                }
            } else {
                PluginTelegramDebugLogger::log("User not found by users_id=" . $notification['users_id']);
            }
        } else {
            PluginTelegramDebugLogger::log("No users_id in notification data");
        }

        return $addresses;
    }
}