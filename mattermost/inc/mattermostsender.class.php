<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Handles all communication with Mattermost API.
 * Used by both notifications and test messages.
 * Optimized for high-load scenarios with connection reuse and caching.
 */
class PluginMattermostSender
{
    /** @var array Mattermost configuration */
    private $config;
    
    /** @var string|null Cached bot user ID */
    private $botUserId = null;
    
    /** @var \CurlHandle|null Reusable cURL handle */
    private $curlHandle = null;
    
    /** @var array Cache for resolved user IDs */
    private $userCache = [];
    
    /** @var array Cache for resolved DM channel IDs */
    private $channelCache = [];

    /**
     * @param array|null $config Mattermost configuration array
     */
    public function __construct($config = null)
    {
        if ($config === null) {
            $config = GlpiPlugin\Mattermost\Config::getConfig();
        }
        $this->config = $config;
    }

    /**
     * Clean up cURL handle on destruction.
     */
    public function __destruct()
    {
        $this->closeCurlHandle();
    }

    /**
     * Send a message to a Mattermost user or channel.
     * Detects address type: 26-char [a-z0-9] = channel ID, otherwise = username.
     * 
     * @param string $address Mattermost username or channel ID
     * @param string $message Message text in Markdown format
     * @return bool True on success
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
        
        if (PluginMattermostDebugLogger::isEnabled()) {
            PluginMattermostDebugLogger::log("Preview: " . substr($message, 0, 200));
        }

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
     * Find Mattermost user ID by username.
     * Uses cache to avoid repeated API calls.
     * 
     * @param string $username Mattermost username
     * @return string|null User ID or null if not found
     */
    private function getUserIdByUsername($username)
    {
        // Check cache first
        if (isset($this->userCache[$username])) {
            PluginMattermostDebugLogger::log("User cache hit: {$username} -> {$this->userCache[$username]}");
            return $this->userCache[$username];
        }

        $response = $this->apiRequest('GET', 'users/username/' . urlencode($username));
        if ($response['success'] && isset($response['body']['id'])) {
            $userId = $response['body']['id'];
            $this->userCache[$username] = $userId;
            PluginMattermostDebugLogger::log("Found user: {$username} -> {$userId}");
            return $userId;
        }
        
        // Cache negative result too
        $this->userCache[$username] = null;
        PluginMattermostDebugLogger::log("User not found: {$username}");
        return null;
    }

    /**
     * Get the bot's own user ID from Mattermost.
     * Caches the result to avoid repeated API calls.
     * 
     * @return string|null Bot user ID or null if not available
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
     * Create or get existing DM channel with a user.
     * Uses cache to avoid repeated API calls.
     * 
     * @param string $username Mattermost username
     * @return string|null Channel ID or null on failure
     */
    private function getDirectChannelId($username)
    {
        // Check cache first
        if (isset($this->channelCache[$username])) {
            PluginMattermostDebugLogger::log("Channel cache hit: {$username} -> {$this->channelCache[$username]}");
            return $this->channelCache[$username];
        }

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
            $channelId = $response['body']['id'];
            $this->channelCache[$username] = $channelId;
            PluginMattermostDebugLogger::log("DM channel: {$channelId}");
            return $channelId;
        }
        
        // Cache negative result
        $this->channelCache[$username] = null;
        return null;
    }

    /**
     * Post a message to a Mattermost channel.
     * 
     * @param string $channelId Mattermost channel ID
     * @param string $message Message text in Markdown format
     * @return bool True on success
     */
    private function sendToChannel($channelId, $message)
    {
        $response = $this->apiRequest('POST', 'posts', [
            'channel_id' => $channelId,
            'message'    => $message,
        ]);

        if (PluginMattermostDebugLogger::isEnabled()) {
            PluginMattermostDebugLogger::log("API response: HTTP {$response['http_code']}", [
                'success' => $response['success'],
                'post_id' => $response['body']['id'] ?? 'N/A'
            ]);
        }

        return $response['success'];
    }

    /**
     * Make an HTTP request to Mattermost API v4.
     * Uses reusable cURL handle for better performance.
     * 
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $endpoint API endpoint path
     * @param mixed $data Request body data (will be JSON encoded)
     * @return array Response data with success, http_code, and body
     * @throws \RuntimeException On cURL errors
     */
    private function apiRequest($method, $endpoint, $data = null)
    {
        $url = rtrim($this->config['server_url'], '/') . '/api/v4/' . ltrim($endpoint, '/');
        
        PluginMattermostDebugLogger::log("API: {$method} {$url}");

        $ch = $this->getCurlHandle();
        
        // Set URL for this request
        curl_setopt($ch, CURLOPT_URL, $url);
        
        // Set HTTP method
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data !== null) {
                $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, '');
            }
        } else {
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $curlInfo = curl_getinfo($ch);

        if (PluginMattermostDebugLogger::isEnabled()) {
            PluginMattermostDebugLogger::log("Response: HTTP {$httpCode} | " . 
                round($curlInfo['total_time'], 3) . "s | " . 
                strlen($response) . " bytes");
        }

        if ($curlError) {
            PluginMattermostDebugLogger::log("cURL error: {$curlError}");
            $this->closeCurlHandle(); // Close handle on error to start fresh next time
            throw new \RuntimeException("Mattermost API cURL error: {$curlError}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $decoded['message'] ?? 'Unknown error';
            $detailedError = $decoded['detailed_error'] ?? '';
            PluginMattermostDebugLogger::log("API error: {$errorMsg}" . 
                ($detailedError ? " ({$detailedError})" : ""));
        }

        return [
            'success'   => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'body'      => $decoded,
        ];
    }

    /**
     * Get or create reusable cURL handle.
     * Configures common options once for all requests.
     * 
     * @return \CurlHandle Configured cURL handle
     */
    private function getCurlHandle()
    {
        if ($this->curlHandle === null) {
            $this->curlHandle = curl_init();
            curl_setopt_array($this->curlHandle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $this->config['bot_token'],
                    'Content-Type: application/json',
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                // Connection reuse for multiple requests
                CURLOPT_FORBID_REUSE   => false,
                CURLOPT_FRESH_CONNECT  => false,
                // Enable TCP keep-alive
                CURLOPT_TCP_KEEPALIVE  => true,
                CURLOPT_TCP_KEEPIDLE   => 60,
                CURLOPT_TCP_KEEPINTVL  => 30,
            ]);
            
            PluginMattermostDebugLogger::log("Created new cURL handle");
        }
        
        return $this->curlHandle;
    }

    /**
     * Close and clean up cURL handle.
     */
    private function closeCurlHandle()
    {
        if ($this->curlHandle !== null) {
            curl_close($this->curlHandle);
            $this->curlHandle = null;
            PluginMattermostDebugLogger::log("Closed cURL handle");
        }
    }

    /**
     * Clear all caches. Useful for testing or when configuration changes.
     */
    public function clearCache()
    {
        $this->userCache = [];
        $this->channelCache = [];
        $this->botUserId = null;
        PluginMattermostDebugLogger::log("Sender caches cleared");
    }

    /**
     * Send test notification to current user's configured addresses.
     * Static method for use from UI.
     * 
     * @return bool True on success
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

        $message = "**\u{1F9EA} " . __('test_message', 'mattermost') . "**\n\n";
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
