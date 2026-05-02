<?php

if (defined('PLUGIN_MATTERMOST_SETUP_LOADED')) {
    return;
}
define('PLUGIN_MATTERMOST_SETUP_LOADED', true);

define('PLUGIN_MATTERMOST_VERSION', '1.1.0');
define('PLUGIN_MATTERMOST_MIN_GLPI', '11.0.0');

function plugin_init_mattermost()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['mattermost'] = true;

    /* Убираем эту строку — старый способ не работает
    $PLUGIN_HOOKS['add_language']['mattermost'] = ['en_US', 'ru_RU'];
    */

    if (!Plugin::isPluginActive('mattermost')) {
        return;
    }

    // Подключаем файлы ДО использования перевода
    require_once __DIR__ . '/inc/debuglogger.class.php';
    require_once __DIR__ . '/inc/htmltomarkdown.class.php';
    require_once __DIR__ . '/inc/mattermostsender.class.php';
    require_once __DIR__ . '/inc/notificationmattermostsetting.class.php';
    require_once __DIR__ . '/inc/notificationeventmattermost.class.php';
    require_once __DIR__ . '/inc/notificationmattermost.class.php';

    // Инициализируем gettext для плагина
    bindtextdomain('mattermost', __DIR__ . '/locales');
    textdomain('mattermost');

    Notification_NotificationTemplate::registerMode(
        'mattermost',
        __('notification_mode', 'mattermost'),
        'mattermost'
    );

    $PLUGIN_HOOKS['notification_settings']['mattermost'] = [
        PluginMattermostNotificationMattermostSetting::class
    ];
    $PLUGIN_HOOKS['notification_events']['mattermost'] = [
        PluginMattermostNotificationEventMattermost::class
    ];
    $PLUGIN_HOOKS['notification_notifications']['mattermost'] = [
        PluginMattermostNotificationMattermost::class
    ];

    $PLUGIN_HOOKS['post_item_form']['mattermost'] = 'plugin_mattermost_post_item_form';
    
    $PLUGIN_HOOKS['pre_item_update']['mattermost'] = [
        'User' => 'plugin_mattermost_pre_item_update'
    ];
    $PLUGIN_HOOKS['pre_item_add']['mattermost'] = [
        'User' => 'plugin_mattermost_pre_item_add'
    ];
}

// Убираем функцию plugin_mattermost_load_language — она больше не нужна

function plugin_version_mattermost()
{
    return [
        'name'         => 'Mattermost',
        'version'      => PLUGIN_MATTERMOST_VERSION,
        'author'       => 'GLPI Community',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/your-org/glpi-mattermost',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_MATTERMOST_MIN_GLPI]
        ]
    ];
}

function plugin_mattermost_check_prerequisites()
{
    return true;
}

function plugin_mattermost_check_config($verbose = false)
{
    return true;
}

function plugin_mattermost_post_item_form($params)
{
    $item = $params['item'] ?? null;
    if ($item instanceof User) {
        plugin_mattermost_user_form($params);
    }
}

function plugin_mattermost_user_form($params)
{
    $item = $params['item'];
    if (!$item instanceof User) {
        return;
    }

    $mattermostId = $item->fields['mattermost_id'] ?? '';
    $mattermostChannelId = $item->fields['mattermost_channel_id'] ?? '';

    echo '</tbody></table>';
    echo '<table class="tab_cadre_fixe" style="width: 100%;">';
    echo '<tr><td colspan="2"><hr style="margin: 0.5rem 0;"></td></tr>';
    echo '<tr class="tab_bg_1">';
    echo '<td style="width: 50%; text-align: center;">';
    echo '<div style="display: inline-block; text-align: left;">';
    echo '<label for="mattermost_id">' . __('mattermost_username', 'mattermost') . '</label><br>';
    echo Html::input('mattermost_id', [
        'value' => $mattermostId,
        'size'  => 30
    ]);
    echo '<div style="line-height: 1.2; margin-top: 2px; font-size: 0.8rem; color: #666;">';
    echo '<i>' . __('dm_hint', 'mattermost') . '</i><br>';
    echo '<i>' . __('leave_empty', 'mattermost') . '</i>';
    echo '</div>';
    echo '</div>';
    echo '</td>';
    echo '<td style="width: 50%; text-align: center;">';
    echo '<div style="display: inline-block; text-align: left;">';
    echo '<label for="mattermost_channel_id">' . __('mattermost_channel_id', 'mattermost') . '</label><br>';
    echo Html::input('mattermost_channel_id', [
        'value' => $mattermostChannelId,
        'size'  => 30
    ]);
    echo '<div style="line-height: 1.2; margin-top: 2px; font-size: 0.8rem; color: #666;">';
    echo '<i>' . __('channel_hint', 'mattermost') . '</i><br>';
    echo '<i>' . __('leave_empty', 'mattermost') . '</i>';
    echo '</div>';
    echo '</div>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<table class="tab_cadre_fixe">';
    echo '<tbody>';
}

function plugin_mattermost_pre_item_update($item)
{
    if ($item instanceof User) {
        if (isset($item->input['mattermost_id'])) {
            $item->input['mattermost_id'] = trim($item->input['mattermost_id']);
        }
        if (isset($item->input['mattermost_channel_id'])) {
            $item->input['mattermost_channel_id'] = trim($item->input['mattermost_channel_id']);
        }
    }
}

function plugin_mattermost_pre_item_add($item)
{
    if ($item instanceof User) {
        if (isset($_POST['mattermost_id'])) {
            $item->input['mattermost_id'] = trim($_POST['mattermost_id']);
        }
        if (isset($_POST['mattermost_channel_id'])) {
            $item->input['mattermost_channel_id'] = trim($_POST['mattermost_channel_id']);
        }
    }
}