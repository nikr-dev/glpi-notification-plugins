<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Handles single Mattermost notification.
 */
class PluginMattermostNotificationMattermost implements NotificationInterface
{
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
        $addresses = $this->getUserAddresses($options);
        if (empty($addresses)) {
            return false;
        }

        $config = GlpiPlugin\Mattermost\Config::getConfig();
        if (empty($config['server_url']) || empty($config['bot_token'])) {
            return false;
        }

        PluginMattermostDebugLogger::logSeparator();
        PluginMattermostDebugLogger::log("=== SINGLE NOTIFICATION ===");
        PluginMattermostDebugLogger::log("Addresses:", $addresses);

        $message = '';
        $subject = $options['subject'] ?? $options['fromname'] ?? '';

        if (!empty($options['body_html'])) {
            $message = PluginMattermostHtmlToMarkdown::convert($options['body_html']);
        } elseif (!empty($options['content_html'])) {
            $message = PluginMattermostHtmlToMarkdown::convert($options['content_html']);
        } elseif (!empty($options['body_text'])) {
            $message = $options['body_text'];
        } elseif (!empty($options['content_text'])) {
            $message = $options['content_text'];
        }

        if (empty($message)) return false;

        if (!empty($subject)) {
            $message = "**{$subject}**\n\n" . $message;
        }

        $sender = new PluginMattermostSender($config);
        $success = false;

        foreach ($addresses as $address) {
            try {
                if ($sender->sendMessage($address, $message)) $success = true;
            } catch (\Exception $e) {
                PluginMattermostDebugLogger::log("Failed: {$address} - " . $e->getMessage());
            }
        }

        PluginMattermostDebugLogger::log("Result: " . ($success ? "SUCCESS" : "FAILED"));
        PluginMattermostDebugLogger::logSeparator();
        return $success;
    }

    /**
     * Get all Mattermost addresses from GLPI user profile.
     * Checks users_id first, then mattermost_id string.
     */
    private function getUserAddresses($options)
    {
        global $DB;
        $addresses = [];
        $to = $options['to'] ?? '';

        $user = new User();
        $found = false;

        // Try by users_id if available
        if (!$found && !empty($options['users_id'])) {
            $found = $user->getFromDB($options['users_id']);
        }

        // Try by numeric to
        if (!$found && is_numeric($to)) {
            $found = $user->getFromDB($to);
        }

        // Try by mattermost_id string
        if (!$found && !empty($to)) {
            $iterator = $DB->request([
                'FROM'  => 'glpi_users',
                'WHERE' => ['mattermost_id' => $to],
                'LIMIT' => 1
            ]);
            if ($row = $iterator->current()) {
                $found = $user->getFromDB($row['id']);
            }
        }

        // Try by items_id (ticket author) as last resort
        if (!$found && !empty($options['items_id'])) {
            $ticket = new Ticket();
            if ($ticket->getFromDB($options['items_id'])) {
                $requesterId = $ticket->fields['users_id_recipient'] ?? 0;
                if ($requesterId) {
                    $found = $user->getFromDB($requesterId);
                }
            }
        }

        if (!$found) return $addresses;

        PluginMattermostDebugLogger::log("User found:", [
            'id' => $user->fields['id'],
            'name' => $user->fields['name'] ?? 'unknown',
            'mattermost_id' => $user->fields['mattermost_id'] ?: 'NOT SET',
            'mattermost_channel_id' => $user->fields['mattermost_channel_id'] ?: 'NOT SET'
        ]);

        if (!empty($user->fields['mattermost_id'])) $addresses[] = $user->fields['mattermost_id'];
        if (!empty($user->fields['mattermost_channel_id'])) $addresses[] = $user->fields['mattermost_channel_id'];

        return $addresses;
    }
}