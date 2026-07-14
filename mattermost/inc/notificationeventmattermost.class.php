<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Handles bulk Mattermost notifications from GLPI notification system.
 */
class PluginMattermostNotificationEventMattermost extends NotificationEventAbstract implements NotificationEventInterface
{
    public static function getTargetFieldName()
    {
        return 'mattermost_id';
    }

    public static function getTargetField(&$data)
    {
        $field = self::getTargetFieldName();

        if (isset($data['users_id'])) {
            $user = new User();
            if ($user->getFromDB($data['users_id'])) {
                // Fill fields from user profile
                $data[$field] = $user->fields['mattermost_id'] ?? '';
                $data['mattermost_channel_id'] = $user->fields['mattermost_channel_id'] ?? '';
                
                // If mattermost_id is empty but channel_id is filled,
                // use channel_id as target field so GLPI doesn't filter out the notification
                if (empty($data[$field]) && !empty($data['mattermost_channel_id'])) {
                    $data[$field] = $data['mattermost_channel_id'];
                }
            }
        }

        if (!isset($data[$field])) {
            $data[$field] = '';
        }
        if (!isset($data['mattermost_channel_id'])) {
            $data['mattermost_channel_id'] = '';
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
        return ['mattermost_id' => ''];
    }

    public static function getEntityAdminsData($entity)
    {
        return [];
    }

    public static function send(array $data)
    {
        $config = GlpiPlugin\Mattermost\Config::getConfig();
        if (empty($config['server_url']) || empty($config['bot_token'])) {
            return false;
        }

        $sender = new PluginMattermostSender($config);
        $sent = false;
        $hasNotifications = false;

        foreach ($data as $index => $notification) {
            // Get addresses for this recipient using their users_id
            $addresses = self::getAddressesForRecipient($notification);
            if (empty($addresses)) {
                continue;
            }

            if (!$hasNotifications) {
                $hasNotifications = true;
                PluginMattermostDebugLogger::logSeparator();
                PluginMattermostDebugLogger::log("=== BULK NOTIFICATIONS | " . count($data) . " items ===");
            }

            PluginMattermostDebugLogger::log("--- Notification #{$index} ---");
            PluginMattermostDebugLogger::log("Recipient users_id: " . ($notification['users_id'] ?? 'N/A'));
            PluginMattermostDebugLogger::log("Addresses to send:", $addresses);

            $message = '';
            $subject = $notification['name'] ?? $notification['subject'] ?? '';

            if (!empty($notification['body_html'])) {
                $message = PluginMattermostHtmlToMarkdown::convert($notification['body_html']);
            } elseif (!empty($notification['content_html'])) {
                $message = PluginMattermostHtmlToMarkdown::convert($notification['content_html']);
            } elseif (!empty($notification['body_text'])) {
                $message = $notification['body_text'];
            }

            if (empty($message)) continue;

            if (!empty($subject)) {
                $message = "**{$subject}**\n\n" . $message;
            }

            foreach ($addresses as $address) {
                try {
                    if ($sender->sendMessage($address, $message)) $sent = true;
                } catch (\Exception $e) {
                    PluginMattermostDebugLogger::log("Failed: {$address} - " . $e->getMessage());
                }
            }
        }

        if ($hasNotifications) {
            PluginMattermostDebugLogger::log("Result: " . ($sent ? "SUCCESS" : "FAILED"));
            PluginMattermostDebugLogger::logSeparator();
        }

        return $sent;
    }

    /**
     * Get Mattermost addresses for recipient by their GLPI users_id.
     * Simply reads mattermost_id and mattermost_channel_id from user profile.
     * 
     * @param array $notification Notification data from GLPI
     * @return array Array of Mattermost addresses
     */
    private static function getAddressesForRecipient($notification)
    {
        $addresses = [];

        // Get recipient by users_id - this is the GLPI user ID
        if (!empty($notification['users_id'])) {
            $user = new User();
            if ($user->getFromDB($notification['users_id'])) {
                PluginMattermostDebugLogger::log("User found by users_id=" . $notification['users_id'] . ":", [
                    'id' => $user->fields['id'],
                    'name' => $user->fields['name'] ?? 'unknown',
                    'mattermost_id' => $user->fields['mattermost_id'] ?: 'EMPTY',
                    'mattermost_channel_id' => $user->fields['mattermost_channel_id'] ?: 'EMPTY'
                ]);

                // Collect ALL non-empty addresses from user profile
                if (!empty($user->fields['mattermost_id'])) {
                    $addresses[] = $user->fields['mattermost_id'];
                }
                if (!empty($user->fields['mattermost_channel_id'])) {
                    $addresses[] = $user->fields['mattermost_channel_id'];
                }

                if (empty($addresses)) {
                    PluginMattermostDebugLogger::log("User has no Mattermost addresses configured");
                }
            } else {
                PluginMattermostDebugLogger::log("User not found by users_id=" . $notification['users_id']);
            }
        } else {
            PluginMattermostDebugLogger::log("No users_id in notification data");
        }

        return array_unique($addresses);
    }
}
