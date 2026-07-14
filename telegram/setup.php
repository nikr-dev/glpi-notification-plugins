<?php

if (defined('PLUGIN_TELEGRAM_SETUP_LOADED')) {
    return;
}
define('PLUGIN_TELEGRAM_SETUP_LOADED', true);

define('PLUGIN_TELEGRAM_VERSION', '1.0.0');
define('PLUGIN_TELEGRAM_MIN_GLPI', '11.0.0');

function plugin_init_telegram()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['telegram'] = true;

    if (!Plugin::isPluginActive('telegram')) {
        return;
    }

    // Подключаем файлы
    require_once __DIR__ . '/inc/debuglogger.class.php';
    require_once __DIR__ . '/inc/htmltotelegramhtml.class.php';
    require_once __DIR__ . '/inc/telegramsender.class.php';
    require_once __DIR__ . '/inc/notificationtelegramsetting.class.php';
    require_once __DIR__ . '/inc/notificationeventtelegram.class.php';
    require_once __DIR__ . '/inc/notificationtelegram.class.php';

    // Инициализируем gettext для плагина
    bindtextdomain('telegram', __DIR__ . '/locales');
    textdomain('telegram');

    Notification_NotificationTemplate::registerMode(
        'telegram',
        __('notification_mode', 'telegram'),
        'telegram'
    );

    $PLUGIN_HOOKS['notification_settings']['telegram'] = [
        PluginTelegramNotificationTelegramSetting::class
    ];
    $PLUGIN_HOOKS['notification_events']['telegram'] = [
        PluginTelegramNotificationEventTelegram::class
    ];
    $PLUGIN_HOOKS['notification_notifications']['telegram'] = [
        PluginTelegramNotificationTelegram::class
    ];

    $PLUGIN_HOOKS['post_item_form']['telegram'] = 'plugin_telegram_post_item_form';
    
    $PLUGIN_HOOKS['pre_item_update']['telegram'] = [
        'User' => 'plugin_telegram_pre_item_update'
    ];
    $PLUGIN_HOOKS['pre_item_add']['telegram'] = [
        'User' => 'plugin_telegram_pre_item_add'
    ];
}

function plugin_version_telegram()
{
    return [
        'name'         => 'Telegram',
        'version'      => PLUGIN_TELEGRAM_VERSION,
        'author'       => 'GLPI Community',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/your-org/glpi-telegram',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_TELEGRAM_MIN_GLPI]
        ]
    ];
}

function plugin_telegram_check_prerequisites()
{
    return true;
}

function plugin_telegram_check_config($verbose = false)
{
    return true;
}

function plugin_telegram_post_item_form($params)
{
    $item = $params['item'] ?? null;
    if ($item instanceof User) {
        plugin_telegram_user_form($params);
    }
}

function plugin_telegram_user_form($params)
{
    $item = $params['item'];
    if (!$item instanceof User) {
        return;
    }

    $telegramId = $item->fields['telegram_id'] ?? '';
    $telegramChatId = $item->fields['telegram_chat_id'] ?? '';
    $telegramThreadId = $item->fields['telegram_thread_id'] ?? '';

    echo '</tbody></table>';
    echo '<table class="tab_cadre_fixe" style="width: 100%;">';
    echo '<tr><td colspan="3"><hr style="margin: 0.5rem 0;"></td></tr>';
    echo '<tr class="tab_bg_1">';
    
    // Поле 1: User ID
    echo '<td style="width: 33%; text-align: center;">';
    echo '<div style="display: inline-block; text-align: left;">';
    echo '<label for="telegram_id">' . __('telegram_user_id', 'telegram') . '</label><br>';
    echo Html::input('telegram_id', [
        'value' => $telegramId,
        'size'  => 20,
        'placeholder' => '123456789'
    ]);
    echo '<div style="line-height: 1.2; margin-top: 2px; font-size: 0.8rem; color: #666;">';
    echo '<i>' . __('dm_hint', 'telegram') . '</i><br>';
    echo '<i>' . __('leave_empty', 'telegram') . '</i>';
    echo '</div>';
    echo '</div>';
    echo '</td>';
    
    // Поле 2: Chat ID
    echo '<td style="width: 33%; text-align: center;">';
    echo '<div style="display: inline-block; text-align: left;">';
    echo '<label for="telegram_chat_id">' . __('telegram_chat_id', 'telegram') . '</label><br>';
    echo Html::input('telegram_chat_id', [
        'value' => $telegramChatId,
        'size'  => 20,
        'placeholder' => '-1001234567890'
    ]);
    echo '<div style="line-height: 1.2; margin-top: 2px; font-size: 0.8rem; color: #666;">';
    echo '<i>' . __('channel_hint', 'telegram') . '</i><br>';
    echo '<i>' . __('leave_empty', 'telegram') . '</i>';
    echo '</div>';
    echo '</div>';
    echo '</td>';
    
    // Поле 3: Thread ID (Topic)
    echo '<td style="width: 33%; text-align: center;">';
    echo '<div style="display: inline-block; text-align: left;">';
    echo '<label for="telegram_thread_id">' . __('telegram_thread_id', 'telegram') . '</label><br>';
    echo Html::input('telegram_thread_id', [
        'value' => $telegramThreadId,
        'size'  => 20,
        'type'  => 'number',
        'placeholder' => '12345'
    ]);
    echo '<div style="line-height: 1.2; margin-top: 2px; font-size: 0.8rem; color: #666;">';
    echo '<i>' . __('thread_hint', 'telegram') . '</i><br>';
    echo '<i>' . __('thread_usage_hint', 'telegram') . '</i>';
    echo '</div>';
    echo '</div>';
    echo '</td>';
    
    echo '</tr>';
    echo '</table>';
    echo '<table class="tab_cadre_fixe">';
    echo '<tbody>';
}

function plugin_telegram_pre_item_update($item)
{
    if ($item instanceof User) {
        if (isset($item->input['telegram_id'])) {
            $item->input['telegram_id'] = trim($item->input['telegram_id']);
        }
        if (isset($item->input['telegram_chat_id'])) {
            $item->input['telegram_chat_id'] = trim($item->input['telegram_chat_id']);
        }
        if (isset($item->input['telegram_thread_id'])) {
            $value = trim($item->input['telegram_thread_id']);
            $item->input['telegram_thread_id'] = !empty($value) ? (int)$value : null;
        }
    }
}

function plugin_telegram_pre_item_add($item)
{
    if ($item instanceof User) {
        if (isset($_POST['telegram_id'])) {
            $item->input['telegram_id'] = trim($_POST['telegram_id']);
        }
        if (isset($_POST['telegram_chat_id'])) {
            $item->input['telegram_chat_id'] = trim($_POST['telegram_chat_id']);
        }
        if (isset($_POST['telegram_thread_id'])) {
            $value = trim($_POST['telegram_thread_id']);
            $item->input['telegram_thread_id'] = !empty($value) ? (int)$value : null;
        }
    }
}