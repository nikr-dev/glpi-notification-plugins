<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginMattermostDebugLogger
{
    private static $logFile = null;
    private static $enabled = null;
    private static $initialized = false;

    private static function init()
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        $config = GlpiPlugin\Mattermost\Config::getConfig();
        self::$enabled = !empty($config['debug_mode']) && (int)$config['debug_mode'] === 1;

        if (!self::$enabled) {
            return;
        }

        $logDir = GLPI_LOG_DIR;
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        self::$logFile = $logDir . '/mattermost-debug.log';

        if (!file_exists(self::$logFile)) {
            @touch(self::$logFile);
            if (file_exists(self::$logFile)) {
                @chmod(self::$logFile, 0644);
            }
        }
    }

    /**
     * Check if debug logging is enabled.
     * Use this to avoid unnecessary work when debug is off.
     * 
     * @return bool True if debug mode is enabled
     */
    public static function isEnabled()
    {
        self::init();
        return self::$enabled;
    }

    public static function log($message, $data = null)
    {
        self::init();

        if (!self::$enabled) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $pid = getmypid();
        $logEntry = "[{$timestamp}] [PID:{$pid}] {$message}";

        if ($data !== null) {
            if (is_string($data)) {
                $logEntry .= "\n  " . $data;
            } else {
                $logEntry .= "\n  " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
        }

        $logEntry .= "\n";

        if (self::$logFile && is_writable(self::$logFile)) {
            @file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
    }

    public static function logSeparator()
    {
        self::log(str_repeat('-', 71));
    }
}
