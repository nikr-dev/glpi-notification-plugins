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
                $data[$field] = $user->fields['mattermost_id'] ?? '';
                $channelId = $user->fields['mattermost_channel_id'] ?? '';
                if (!empty($channelId)) {
                    $data['mattermost_channel_id'] = $channelId;
                }
            }
        }

        if (!isset($data[$field])) {
            $data[$field] = '';
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
            $addresses = self::getAddresses($notification);
            if (empty($addresses)) {
                continue;
            }

            if (!$hasNotifications) {
                $hasNotifications = true;
                PluginMattermostDebugLogger::logSeparator();
                PluginMattermostDebugLogger::log("=== BULK NOTIFICATIONS | " . count($data) . " items ===");
            }

            PluginMattermostDebugLogger::log("--- #{$index} ---");
            PluginMattermostDebugLogger::log("Addresses:", $addresses);

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

    private static function getAddresses($notification)
    {
        global $DB;
        $addresses = [];

        if (!empty($notification['users_id'])) {
            $user = new User();
            if ($user->getFromDB($notification['users_id'])) {
                if (!empty($user->fields['mattermost_id'])) $addresses[] = $user->fields['mattermost_id'];
                if (!empty($user->fields['mattermost_channel_id'])) $addresses[] = $user->fields['mattermost_channel_id'];
            }
        }

        if (empty($addresses)) {
            if (!empty($notification['mattermost_id'])) {
                $addresses[] = $notification['mattermost_id'];
                
                $iterator = $DB->request([
                    'FROM'  => 'glpi_users',
                    'WHERE' => ['mattermost_id' => $notification['mattermost_id']],
                    'LIMIT' => 1
                ]);
                if ($row = $iterator->current()) {
                    $user = new User();
                    if ($user->getFromDB($row['id'])) {
                        if (!empty($user->fields['mattermost_channel_id'])) {
                            $addresses[] = $user->fields['mattermost_channel_id'];
                        }
                    }
                }
            }
            if (!empty($notification['mattermost_channel_id'])) {
                $addresses[] = $notification['mattermost_channel_id'];
            }
        }

        return array_unique($addresses);
    }
}