<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Handles single Telegram notification.
 */
class PluginTelegramNotificationTelegram implements NotificationInterface
{
    private static $userCache = [];
    private static $sender = null;
    private static $config = null;

    public static function check($value, $options = [])
    {
        return true;
    }

    public static function isHtml()
    {
        return true;
    }

    public static function testNotification()
    {
        return PluginTelegramSender::testNotification();
    }

    public function sendNotification($options = [])
    {
        if (PluginTelegramDebugLogger::isEnabled()) {
            PluginTelegramDebugLogger::logSeparator();
            PluginTelegramDebugLogger::log("=== SINGLE NOTIFICATION ===");
            PluginTelegramDebugLogger::log("GLPI options:", $options);
        }

        $addresses = $this->getRecipientAddresses($options);
        if (empty($addresses)) {
            PluginTelegramDebugLogger::log("No Telegram addresses found for recipient");
            return false;
        }

        if (self::$config === null) {
            self::$config = GlpiPlugin\Telegram\Config::getConfig();
        }

        if (empty(self::$config['bot_token'])) {
            PluginTelegramDebugLogger::log("Telegram not configured");
            return false;
        }

        $message = $this->buildMessage($options);
        if (empty($message)) {
            PluginTelegramDebugLogger::log("Empty message body");
            return false;
        }

        // Trim and limit message length
        $message = trim($message);
        if (mb_strlen($message) > 4000) {
            $message = mb_substr($message, 0, 3997) . '...';
        }

        if (PluginTelegramDebugLogger::isEnabled()) {
            PluginTelegramDebugLogger::log("Sending to " . count($addresses) . " address(es)");
            PluginTelegramDebugLogger::log("Message preview: " . substr($message, 0, 200) . (strlen($message) > 200 ? '...' : ''));
        }

        if (self::$sender === null) {
            self::$sender = new PluginTelegramSender(self::$config);
        }

        $success = false;

        foreach ($addresses as $addr) {
            try {
                PluginTelegramDebugLogger::log("Sending to: {$addr['address']}" . ($addr['thread_id'] ? " (thread: {$addr['thread_id']})" : ""));
                if (self::$sender->sendMessage($addr['address'], $message, $addr['thread_id'])) {
                    $success = true;
                }
            } catch (\Exception $e) {
                PluginTelegramDebugLogger::log("Failed: {$addr['address']} - " . $e->getMessage());
            }
        }

        if (PluginTelegramDebugLogger::isEnabled()) {
            PluginTelegramDebugLogger::log("Result: " . ($success ? "SUCCESS" : "FAILED"));
            PluginTelegramDebugLogger::logSeparator();
        }

        return $success;
    }

    private function buildMessage($options)
    {
        $message = $options['body_html'] 
            ?? $options['content_html'] 
            ?? $options['body_text'] 
            ?? $options['content_text'] 
            ?? '';

        if (empty($message)) {
            return '';
        }

        // Convert HTML to Telegram HTML
        if (!empty($options['body_html']) || !empty($options['content_html'])) {
            $message = PluginTelegramHtmlToTelegramHtml::convert($message);
        }

        // Add subject if present (as bold)
        $subject = $options['subject'] ?? $options['fromname'] ?? '';
        if (!empty($subject)) {
            $subject = trim($subject);
            $message = "<b>" . $subject . "</b>\n\n" . $message;
        }

        return $message;
    }

    private function getRecipientAddresses($options)
    {
        $to = $options['to'] ?? '';

        if (empty($to)) {
            PluginTelegramDebugLogger::log("No 'to' field in options");
            return [];
        }

        if (isset(self::$userCache[$to])) {
            if (PluginTelegramDebugLogger::isEnabled()) {
                PluginTelegramDebugLogger::log("Cache hit for '{$to}'");
            }
            return self::$userCache[$to];
        }

        PluginTelegramDebugLogger::log("Looking up user by 'to'='{$to}'");

        $user = $this->findUserByTelegramField($to);
        if ($user === null) {
            PluginTelegramDebugLogger::log("User not found for '{$to}'");
            self::$userCache[$to] = [];
            return [];
        }

        if (PluginTelegramDebugLogger::isEnabled()) {
            PluginTelegramDebugLogger::log("Found user:", [
                'id' => $user['id'],
                'name' => $user['name'],
                'realname' => $user['realname'] ?? '',
                'firstname' => $user['firstname'] ?? '',
                'telegram_id' => $user['telegram_id'] ?: 'EMPTY',
                'telegram_chat_id' => $user['telegram_chat_id'] ?: 'EMPTY',
                'telegram_thread_id' => $user['telegram_thread_id'] ?: 'NONE'
            ]);
        }

        $addresses = [];
        
        if (!empty($user['telegram_id'])) {
            $addresses[] = ['address' => $user['telegram_id'], 'thread_id' => null];
        }
        
        if (!empty($user['telegram_chat_id'])) {
            $addresses[] = [
                'address' => $user['telegram_chat_id'], 
                'thread_id' => $user['telegram_thread_id'] ?? null
            ];
        }

        self::$userCache[$to] = $addresses;
        return $addresses;
    }

    private function findUserByTelegramField($value)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['id', 'name', 'realname', 'firstname', 'telegram_id', 'telegram_chat_id', 'telegram_thread_id'],
            'FROM'   => 'glpi_users',
            'WHERE'  => [
                'OR' => [
                    ['telegram_id' => $value],
                    ['telegram_chat_id' => $value]
                ]
            ],
            'LIMIT'  => 1
        ]);

        if ($row = $iterator->current()) {
            if (PluginTelegramDebugLogger::isEnabled()) {
                $matchedField = ($row['telegram_id'] === $value) ? 'telegram_id' : 'telegram_chat_id';
                PluginTelegramDebugLogger::log("Found by {$matchedField}='{$value}'");
            }
            return $row;
        }

        return null;
    }

    public static function clearCache()
    {
        self::$userCache = [];
        self::$sender = null;
        self::$config = null;
        PluginTelegramDebugLogger::log("Caches cleared");
    }
}