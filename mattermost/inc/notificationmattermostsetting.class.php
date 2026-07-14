<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginMattermostNotificationMattermostSetting extends NotificationSetting
{
    public static function getTypeName($nb = 0)
    {
        return __('followups_configuration', 'mattermost');
    }

    public function getEnableLabel()
    {
        return __('enable_followups', 'mattermost');
    }

    public static function getMode()
    {
        return 'mattermost';
    }

    public static function testNotification()
    {
        return PluginMattermostSender::testNotification();
    }

    public function showFormConfig($options = [])
    {
        $config = GlpiPlugin\Mattermost\Config::getConfig();

        echo "<form action='" . Toolbox::getItemTypeFormURL(__CLASS__) . "' method='post'>";
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'><th colspan='2'>" . __('setup', 'mattermost') . "</th></tr>";

        // Server URL
        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='server_url'>" . __('server_url', 'mattermost') . "</label></td>";
        echo "<td>";
        echo Html::input('server_url', [
            'value' => $config['server_url'] ?? '',
            'size'  => 50,
            'placeholder' => 'https://mattermost.your-company.com'
        ]);
        echo "</td></tr>";

        // Bot Token
        echo "<tr class='tab_bg_1'>";
        echo "<td><label for='bot_token'>" . __('bot_token', 'mattermost') . "</label></td>";
        echo "<td>";
        echo Html::input('bot_token', [
            'value' => $config['bot_token'] ?? '',
            'size'  => 50,
            'type'  => 'password'
        ]);
        echo "</td></tr>";

        // Debug Mode
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('debug_mode', 'mattermost') . "</td>";
        echo "<td>";

        $checked = (!empty($config['debug_mode']) && $config['debug_mode'] == 1) ? 'checked' : '';

        echo "<input type='hidden' name='debug_mode' value='0'>";
        echo "<input type='checkbox' name='debug_mode' value='1' {$checked}>";
        echo "&nbsp;" . __('log_to_file', 'mattermost');
        echo "<br><small>" . __('log_file_location', 'mattermost') . " " . GLPI_LOG_DIR . "/mattermost-debug.log</small>";
        echo "</td></tr>";

        // Статус
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('status', 'mattermost') . "</td>";
        echo "<td>";
        if (!empty($config['server_url']) && !empty($config['bot_token'])) {
            echo "<span style='color: green;'>" . __('configured_active', 'mattermost') . "</span>";
        } else {
            echo "<span style='color: red;'>" . __('not_configured', 'mattermost') . "</span>";
        }
        echo "</td></tr>";

        echo "</table>";

        $options['candel'] = false;
        $options['addbuttons'] = [
            'test_mattermost_send' => __('test_send', 'mattermost')
        ];

        echo "<input type='hidden' name='id' value='1'>";
        $this->showFormButtons($options);
        echo "</form>";
    }
}