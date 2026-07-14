<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Handles all communication with Telegram Bot API.
 * Uses HTML parse_mode for better compatibility.
 */
class PluginTelegramSender
{
    private $config;
    private $curlHandle = null;

    public function __construct($config = null)
    {
        if ($config === null) {
            $config = GlpiPlugin\Telegram\Config::getConfig();
        }
        $this->config = $config;
    }

    public function __destruct()
    {
        $this->closeCurlHandle();
    }

    /**
     * Send a message to a Telegram user, group or channel.
     */
    public function sendMessage($address, $message, $threadId = null)
    {
        if (empty($this->config['bot_token'])) {
            PluginTelegramDebugLogger::log("Send skipped: bot token not configured");
            return false;
        }

        if (empty($address) || empty($message)) {
            PluginTelegramDebugLogger::log("Send skipped: empty address or message");
            return false;
        }

        PluginTelegramDebugLogger::log(">>> SENDING to {$address}" . ($threadId ? " (thread: {$threadId})" : "") . " | " . strlen($message) . " bytes");
        
        if (PluginTelegramDebugLogger::isEnabled()) {
            PluginTelegramDebugLogger::log("Preview: " . substr($message, 0, 200));
        }

        try {
            // Try with HTML parse_mode
            $result = $this->sendToTelegram($address, $message, $threadId, 'HTML');
            
            // If HTML fails, try without parse_mode (plain text)
            if (!$result['success']) {
                PluginTelegramDebugLogger::log("HTML parsing failed, sending as plain text");
                $plainMessage = $this->stripHtml($message);
                $result = $this->sendToTelegram($address, $plainMessage, $threadId, null);
            }
            
            PluginTelegramDebugLogger::log(($result['success'] ? "SUCCESS" : "FAILED") . ": {$address}");
            return $result['success'];
            
        } catch (\Exception $e) {
            PluginTelegramDebugLogger::log("Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Strip HTML tags for plain text fallback
     */
    private function stripHtml($text)
    {
        // Convert <br> to newline
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        // Strip all HTML tags
        $text = strip_tags($text);
        // Remove multiple spaces
        $text = preg_replace('/[ \t]+/', ' ', $text);
        // Remove multiple newlines
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
    }

    /**
     * Post a message to Telegram via Bot API.
     */
    private function sendToTelegram($chatId, $message, $threadId = null, $parseMode = 'HTML')
    {
        // Clean message for Telegram
        $cleanMessage = $this->sanitizeMessage($message);
        
        $params = [
            'chat_id' => $chatId,
            'text'    => $cleanMessage,
            'disable_web_page_preview' => true,
        ];
        
        if ($parseMode !== null) {
            $params['parse_mode'] = $parseMode;
        }
        
        if ($threadId !== null && $threadId > 0) {
            $params['message_thread_id'] = $threadId;
            PluginTelegramDebugLogger::log("Using thread_id: {$threadId}");
        }

        $response = $this->apiRequest('sendMessage', $params);

        if (PluginTelegramDebugLogger::isEnabled()) {
            PluginTelegramDebugLogger::log("API response: HTTP {$response['http_code']}", [
                'success' => $response['success'],
                'parse_mode' => $parseMode ?? 'none',
                'message_id' => $response['body']['result']['message_id'] ?? 'N/A'
            ]);
        }

        return $response;
    }

    /**
     * Sanitize message for Telegram
     */
    private function sanitizeMessage($message)
    {
        // Remove null bytes
        $message = str_replace("\0", '', $message);
        
        // Remove control characters except newline and tab
        $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $message);
        
        // Normalize line endings
        $message = str_replace("\r\n", "\n", $message);
        $message = str_replace("\r", "\n", $message);
        
        // Remove excessive newlines
        $message = preg_replace('/\n{4,}/', "\n\n\n", $message);
        
        // Remove zero-width characters
        $message = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $message);
        
        // Fix unclosed tags
        $message = $this->fixHtmlTags($message);
        
        // Truncate if too long (Telegram limit is 4096 chars)
        if (mb_strlen($message) > 4000) {
            $message = mb_substr($message, 0, 3997) . '...';
        }
        
        return $message;
    }

    /**
     * Fix unclosed HTML tags
     */
    private function fixHtmlTags($text)
    {
        // List of allowed tags
        $allowedTags = ['b', 'strong', 'i', 'em', 'u', 's', 'a', 'code', 'pre'];
        
        // Find all opening tags
        preg_match_all('/<(' . implode('|', $allowedTags) . ')(?:\s+[^>]*)?>/i', $text, $openMatches);
        preg_match_all('/<\/(' . implode('|', $allowedTags) . ')>/i', $text, $closeMatches);
        
        $openTags = $openMatches[1] ?? [];
        $closeTags = $closeMatches[1] ?? [];
        
        // Count tags
        $tagCount = [];
        foreach ($allowedTags as $tag) {
            $openCount = 0;
            $closeCount = 0;
            foreach ($openTags as $t) {
                if (strtolower($t) === $tag) $openCount++;
            }
            foreach ($closeTags as $t) {
                if (strtolower($t) === $tag) $closeCount++;
            }
            if ($openCount > $closeCount) {
                // Close unclosed tags
                $text .= '</' . $tag . '>';
            }
        }
        
        return $text;
    }

    /**
     * Make HTTP request to Telegram Bot API
     */
    private function apiRequest($method, $data = null)
    {
        $url = 'https://api.telegram.org/bot' . $this->config['bot_token'] . '/' . $method;
        
        PluginTelegramDebugLogger::log("API: POST {$method}");

        $ch = $this->getCurlHandle();
        curl_setopt($ch, CURLOPT_URL, $url);
        
        if ($data !== null) {
            $postData = http_build_query($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, '');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($curlError) {
            PluginTelegramDebugLogger::log("cURL error: {$curlError}");
            $this->closeCurlHandle();
            throw new \RuntimeException("Telegram API cURL error: {$curlError}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400 || !($decoded['ok'] ?? false)) {
            PluginTelegramDebugLogger::log("API error: " . ($decoded['description'] ?? 'Unknown error'));
            
            return [
                'success'   => false,
                'http_code' => $httpCode,
                'body'      => $decoded,
            ];
        }

        return [
            'success'   => true,
            'http_code' => $httpCode,
            'body'      => $decoded,
        ];
    }

    /**
     * Get reusable cURL handle
     */
    private function getCurlHandle()
    {
        if ($this->curlHandle === null) {
            $this->curlHandle = curl_init();
            curl_setopt_array($this->curlHandle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST          => true,
                CURLOPT_HTTPHEADER    => [
                    'Content-Type: application/x-www-form-urlencoded',
                ],
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FORBID_REUSE   => false,
                CURLOPT_FRESH_CONNECT  => false,
                CURLOPT_TCP_KEEPALIVE  => true,
                CURLOPT_TCP_KEEPIDLE   => 60,
                CURLOPT_TCP_KEEPINTVL  => 30,
            ]);
            
            PluginTelegramDebugLogger::log("Created new cURL handle");
        }
        
        return $this->curlHandle;
    }

    /**
     * Close cURL handle
     */
    private function closeCurlHandle()
    {
        if ($this->curlHandle !== null) {
            curl_close($this->curlHandle);
            $this->curlHandle = null;
            PluginTelegramDebugLogger::log("Closed cURL handle");
        }
    }

    /**
     * Send test notification
     */
    public static function testNotification()
    {
        PluginTelegramDebugLogger::logSeparator();
        PluginTelegramDebugLogger::log("=== TEST NOTIFICATION ===");

        $config = GlpiPlugin\Telegram\Config::getConfig();
        $userId = Session::getLoginUserID();

        if (empty($config['bot_token'])) {
            Session::addMessageAfterRedirect(__('configure_first', 'telegram'), false, ERROR);
            return false;
        }

        $user = new User();
        if (!$user->getFromDB($userId)) {
            return false;
        }

        $addresses = [];
        $telegramId = $user->fields['telegram_id'] ?? '';
        $chatId = $user->fields['telegram_chat_id'] ?? '';
        $threadId = $user->fields['telegram_thread_id'] ?? null;

        if (!empty($telegramId)) {
            $addresses[] = ['address' => $telegramId, 'thread_id' => null];
        }
        if (!empty($chatId)) {
            $addresses[] = ['address' => $chatId, 'thread_id' => $threadId];
        }

        if (empty($addresses)) {
            Session::addMessageAfterRedirect(__('no_addresses_configured', 'telegram'), false, ERROR);
            return false;
        }

        $message = "<b>\u{1F9EA} " . __('test_message', 'telegram') . "</b>\n\n";
        $message .= __('test_body', 'telegram') . "\n\n";
        $message .= "---\n<i>" . sprintf(__('test_footer', 'telegram'), date('Y-m-d H:i:s'), PLUGIN_TELEGRAM_VERSION) . "</i>";

        $sender = new self($config);
        $success = 0;

        foreach ($addresses as $addr) {
            if ($sender->sendMessage($addr['address'], $message, $addr['thread_id'])) $success++;
        }

        PluginTelegramDebugLogger::log("Test result: {$success}/" . count($addresses));

        if ($success > 0) {
            Session::addMessageAfterRedirect(__('test_sent', 'telegram'), false, INFO);
            return true;
        }
        
        Session::addMessageAfterRedirect(__('test_failed', 'telegram'), false, ERROR);
        return false;
    }
}