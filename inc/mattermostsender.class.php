<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Handles all communication with Mattermost API.
 * Used by both notifications and test messages.
 */
class PluginMattermostSender
{
    private $config;
    private $botUserId = null;

    public function __construct($config = null)
    {
        if ($config === null) {
            $config = GlpiPlugin\Mattermost\Config::getConfig();
        }
        $this->config = $config;
    }

    /**
     * Send a message to a Mattermost user or channel.
     * Detects address type: 26-char [a-z0-9] = channel ID, otherwise = username.
     */
    public function sendMessage($address, $message)
    {
        if (empty($this->config['server_url']) || empty($this->config['bot_token'])) {
            PluginMattermostDebugLogger::log("Send skipped: not configured");
            return false;
        }

        if (empty($address) || empty($message)) {
            PluginMattermostDebugLogger::log("Send skipped: empty address or message");
            return false;
        }

        $addressType = preg_match('/^[a-z0-9]{26}$/', $address) ? 'CHANNEL' : 'USER';
        
        PluginMattermostDebugLogger::log(">>> SENDING to {$address} ({$addressType}) | " . strlen($message) . " bytes");
        PluginMattermostDebugLogger::log("Preview: " . substr($message, 0, 200));

        try {
            if ($addressType === 'CHANNEL') {
                $channelId = $address;
            } else {
                $channelId = $this->getDirectChannelId($address);
                if ($channelId === null) {
                    PluginMattermostDebugLogger::log("FAILED: cannot resolve DM channel for {$address}");
                    return false;
                }
            }

            $result = $this->sendToChannel($channelId, $message);
            PluginMattermostDebugLogger::log(($result ? "SUCCESS" : "FAILED") . ": {$address}");
            return $result;
            
        } catch (\Exception $e) {
            PluginMattermostDebugLogger::log("Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Find Mattermost user ID by username
     */
    private function getUserIdByUsername($username)
    {
        $response = $this->apiRequest('GET', 'users/username/' . urlencode($username));
        if ($response['success'] && isset($response['body']['id'])) {
            PluginMattermostDebugLogger::log("Found user: {$username} -> {$response['body']['id']}");
            return $response['body']['id'];
        }
        PluginMattermostDebugLogger::log("User not found: {$username}");
        return null;
    }

    /**
     * Get the bot's own user ID from Mattermost
     */
    private function getBotUserId()
    {
        if ($this->botUserId !== null) {
            return $this->botUserId;
        }

        $response = $this->apiRequest('GET', 'users/me');
        if ($response['success'] && isset($response['body']['id'])) {
            $this->botUserId = $response['body']['id'];
            PluginMattermostDebugLogger::log("Bot ID: {$this->botUserId}");
        }
        return $this->botUserId;
    }

    /**
     * Create or get existing DM channel with a user
     */
    private function getDirectChannelId($username)
    {
        $targetUserId = $this->getUserIdByUsername($username);
        if ($targetUserId === null) {
            return null;
        }

        $botId = $this->getBotUserId();
        if ($botId === null) {
            return null;
        }

        $response = $this->apiRequest('POST', 'channels/direct', [$botId, $targetUserId]);
        if ($response['success'] && isset($response['body']['id'])) {
            PluginMattermostDebugLogger::log("DM channel: {$response['body']['id']}");
            return $response['body']['id'];
        }
        return null;
    }

    /**
     * Post a message to a Mattermost channel
     */
    private function sendToChannel($channelId, $message)
    {
        $response = $this->apiRequest('POST', 'posts', [
            'channel_id' => $channelId,
            'message'    => $message,
        ]);

        PluginMattermostDebugLogger::log("API response: HTTP {$response['http_code']}", [
            'success' => $response['success'],
            'post_id' => $response['body']['id'] ?? 'N/A'
        ]);

        return $response['success'];
    }

    /**
     * Make an HTTP request to Mattermost API v4
     */
    private function apiRequest($method, $endpoint, $data = null)
    {
        $url = rtrim($this->config['server_url'], '/') . '/api/v4/' . ltrim($endpoint, '/');
        PluginMattermostDebugLogger::log("cURL: {$method} {$url}");

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->config['bot_token'],
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        if ($method === 'POST' && $data !== null) {
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        PluginMattermostDebugLogger::log("Response: HTTP {$httpCode} | " . round($curlInfo['total_time'], 3) . "s | " . strlen($response) . " bytes");

        if ($curlError) {
            PluginMattermostDebugLogger::log("cURL error: {$curlError}");
            throw new \RuntimeException("Mattermost API cURL error: {$curlError}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            PluginMattermostDebugLogger::log("API error: " . ($decoded['message'] ?? 'Unknown') . 
                ($decoded['detailed_error'] ? " ({$decoded['detailed_error']})" : ""));
        }

        return [
            'success'   => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'body'      => $decoded,
        ];
    }

    /**
     * Send test notification to current user's configured addresses
     */
    public static function testNotification()
    {
        PluginMattermostDebugLogger::logSeparator();
        PluginMattermostDebugLogger::log("=== TEST NOTIFICATION ===");

        $config = GlpiPlugin\Mattermost\Config::getConfig();
        $userId = Session::getLoginUserID();

        if (empty($config['server_url']) || empty($config['bot_token'])) {
            Session::addMessageAfterRedirect(__('configure_first', 'mattermost'), false, ERROR);
            return false;
        }

        $user = new User();
        if (!$user->getFromDB($userId)) {
            return false;
        }

        $addresses = [];
        $username = $user->fields['mattermost_id'] ?? '';
        $channelId = $user->fields['mattermost_channel_id'] ?? '';

        if (!empty($username)) $addresses[] = $username;
        if (!empty($channelId)) $addresses[] = $channelId;

        if (empty($addresses)) {
            Session::addMessageAfterRedirect(__('no_addresses_configured', 'mattermost'), false, ERROR);
            return false;
        }

        $message = "**🧪 " . __('test_message', 'mattermost') . "**\n\n";
        $message .= __('test_body', 'mattermost') . "\n\n";
        $message .= "---\n*" . sprintf(__('test_footer', 'mattermost'), date('Y-m-d H:i:s'), PLUGIN_MATTERMOST_VERSION) . "*";

        $sender = new self($config);
        $success = 0;

        foreach ($addresses as $address) {
            if ($sender->sendMessage($address, $message)) $success++;
        }

        PluginMattermostDebugLogger::log("Test result: {$success}/" . count($addresses));

        if ($success > 0) {
            Session::addMessageAfterRedirect(__('test_sent', 'mattermost'), false, INFO);
            return true;
        }
        
        Session::addMessageAfterRedirect(__('test_failed', 'mattermost'), false, ERROR);
        return false;
    }
}