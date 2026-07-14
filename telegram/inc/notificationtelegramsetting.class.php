<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginTelegramNotificationTelegramSetting extends NotificationSetting
{
    public static function getTypeName($nb = 0)
    {
        return __('followups_configuration', 'telegram');
    }

    public function getEnableLabel()
    {
        return __('enable_followups', 'telegram');
    }

    public static function getMode()
    {
        return 'telegram';
    }

    public static function testNotification()
    {
        return PluginTelegramSender::testNotification();
    }

    public function showFormConfig($options = [])
    {
        $config = GlpiPlugin\Telegram\Config::getConfig();

        echo "<form action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "' method='post'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'><th colspan='2'>" . __('setup', 'telegram') . "</th></tr>";

        // Bot Token
        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='bot_token'>" . __('bot_token', 'telegram') . "</label></td>";
        echo "<td>";
        echo Html::input('bot_token', [
            'value' => $config['bot_token'] ?? '',
            'size'  => 50,
            'type'  => 'password'
        ]);
        echo "<br><small>" . __('bot_token_hint', 'telegram') . "</small>";
        echo "</td></tr>";

        // Debug Mode
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('debug_mode', 'telegram') . "</td>";
        echo "<td>";

        $checked = (!empty($config['debug_mode']) && $config['debug_mode'] == 1) ? 'checked' : '';

        echo "<input type='hidden' name='debug_mode' value='0'>";
        echo "<input type='checkbox' name='debug_mode' value='1' {$checked}>";
        echo "&nbsp;" . __('log_to_file', 'telegram');
        echo "<br><small>" . __('log_file_location', 'telegram') . " " . GLPI_LOG_DIR . "/telegram-debug.log</small>";
        echo "</td></tr>";

        // Status
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('status', 'telegram') . "</td>";
        echo "<td>";
        if (!empty($config['bot_token'])) {
            echo "<span style='color: green;'>" . __('configured_active', 'telegram') . "</span>";
        } else {
            echo "<span style='color: red;'>" . __('not_configured', 'telegram') . "</span>";
        }
        echo "</td></tr>";

        echo "</table>";

        $options['candel'] = false;
        $options['addbuttons'] = [
            'test_telegram_send' => __('test_send', 'telegram')
        ];

        echo "<input type='hidden' name='id' value='1'>";
        $this->showFormButtons($options);
        echo "</form>";
    }
}