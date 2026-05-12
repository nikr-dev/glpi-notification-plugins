<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Handles single Mattermost notification.
 * Optimized for high-load scenarios with many notifications.
 */
class PluginMattermostNotificationMattermost implements NotificationInterface
{
    /** @var array Cache for GLPI user lookups by Mattermost field value */
    private static $userCache = [];

    /** @var PluginMattermostSender|null Cached sender instance */
    private static $sender = null;

    /** @var array|null Cached Mattermost config */
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
        return PluginMattermostSender::testNotification();
    }

    public function sendNotification($options = [])
    {
        if (PluginMattermostDebugLogger::isEnabled()) {
            PluginMattermostDebugLogger::logSeparator();
            PluginMattermostDebugLogger::log("=== SINGLE NOTIFICATION ===");
            PluginMattermostDebugLogger::log("GLPI options:", $options);
        }

        $addresses = $this->getRecipientAddresses($options);
        if (empty($addresses)) {
            PluginMattermostDebugLogger::log("No Mattermost addresses found for recipient");
            return false;
        }

        // Lazy load and cache config
        if (self::$config === null) {
            self::$config = GlpiPlugin\Mattermost\Config::getConfig();
        }

        if (empty(self::$config['server_url']) || empty(self::$config['bot_token'])) {
            PluginMattermostDebugLogger::log("Mattermost not configured");
            return false;
        }

        $message = $this->buildMessage($options);
        if (empty($message)) {
            PluginMattermostDebugLogger::log("Empty message body");
            return false;
        }

        if (PluginMattermostDebugLogger::isEnabled()) {
            PluginMattermostDebugLogger::log("Sending to " . count($addresses) . " address(es): " . implode(', ', $addresses));
        }

        // Reuse sender instance
        if (self::$sender === null) {
            self::$sender = new PluginMattermostSender(self::$config);
        }

        $success = false;

        foreach ($addresses as $address) {
            try {
                if (self::$sender->sendMessage($address, $message)) {
                    $success = true;
                }
            } catch (\Exception $e) {
                PluginMattermostDebugLogger::log("Failed: {$address} - " . $e->getMessage());
            }
        }

        if (PluginMattermostDebugLogger::isEnabled()) {
            PluginMattermostDebugLogger::log("Result: " . ($success ? "SUCCESS" : "FAILED"));
            PluginMattermostDebugLogger::logSeparator();
        }

        return $success;
    }

    /**
     * Build notification message from GLPI options.
     * Avoids unnecessary conversions for text-only messages.
     * 
     * @param array $options Notification options
     * @return string Formatted message
     */
    private function buildMessage($options)
    {
        // Direct access to avoid multiple array lookups
        $message = $options['body_html'] 
            ?? $options['content_html'] 
            ?? $options['body_text'] 
            ?? $options['content_text'] 
            ?? '';

        if (empty($message)) {
            return '';
        }

        // Only convert if we have HTML
        if (!empty($options['body_html']) || !empty($options['content_html'])) {
            $message = PluginMattermostHtmlToMarkdown::convert($message);
        }

        // Add subject if present
        $subject = $options['subject'] ?? $options['fromname'] ?? '';
        if (!empty($subject)) {
            $message = "**{$subject}**\n\n" . $message;
        }

        return $message;
    }

    /**
     * Get Mattermost addresses for the recipient from GLPI user profile.
     * Uses caching to avoid repeated DB queries for the same user.
     * 
     * @param array $options Notification options from GLPI
     * @return array Array of unique Mattermost addresses
     */
    private function getRecipientAddresses($options)
    {
        $to = $options['to'] ?? '';

        if (empty($to)) {
            return [];
        }

        // Check cache first
        if (isset(self::$userCache[$to])) {
            if (PluginMattermostDebugLogger::isEnabled()) {
                PluginMattermostDebugLogger::log("Cache hit for '{$to}'");
            }
            return self::$userCache[$to];
        }

        PluginMattermostDebugLogger::log("Looking up user by 'to'='{$to}'");

        $user = $this->findUserByMattermostField($to);
        if ($user === null) {
            PluginMattermostDebugLogger::log("User not found for '{$to}'");
            // Cache negative result too to avoid repeated queries
            self::$userCache[$to] = [];
            return [];
        }

        if (PluginMattermostDebugLogger::isEnabled()) {
            PluginMattermostDebugLogger::log("Found user:", [
                'id' => $user['id'],
                'name' => $user['name'],
                'realname' => $user['realname'] ?? '',
                'firstname' => $user['firstname'] ?? '',
                'mattermost_id' => $user['mattermost_id'] ?: 'EMPTY',
                'mattermost_channel_id' => $user['mattermost_channel_id'] ?: 'EMPTY'
            ]);
        }

        // Collect all non-empty addresses
        $addresses = [];
        if (!empty($user['mattermost_id'])) {
            $addresses[] = $user['mattermost_id'];
        }
        if (!empty($user['mattermost_channel_id'])) {
            $addresses[] = $user['mattermost_channel_id'];
        }

        $addresses = array_unique($addresses);
        
        // Cache the result
        self::$userCache[$to] = $addresses;

        return $addresses;
    }

    /**
     * Find GLPI user by Mattermost field value.
     * Uses optimized query with specific column selection.
     * 
     * @param string $value Value to search in mattermost_id or mattermost_channel_id
     * @return array|null User data or null if not found
     */
    private function findUserByMattermostField($value)
    {
        global $DB;

        $iterator = $DB->request([
            'SELECT' => ['id', 'name', 'realname', 'firstname', 'mattermost_id', 'mattermost_channel_id'],
            'FROM'   => 'glpi_users',
            'WHERE'  => [
                'OR' => [
                    ['mattermost_id' => $value],
                    ['mattermost_channel_id' => $value]
                ]
            ],
            'LIMIT'  => 1
        ]);

        if ($row = $iterator->current()) {
            if (PluginMattermostDebugLogger::isEnabled()) {
                $matchedField = ($row['mattermost_id'] === $value) ? 'mattermost_id' : 'mattermost_channel_id';
                PluginMattermostDebugLogger::log("Found by {$matchedField}='{$value}'");
            }
            return $row;
        }

        return null;
    }

    /**
     * Clear static caches. Useful for testing or long-running processes.
     */
    public static function clearCache()
    {
        self::$userCache = [];
        self::$sender = null;
        self::$config = null;
        PluginMattermostDebugLogger::log("Caches cleared");
    }
}
