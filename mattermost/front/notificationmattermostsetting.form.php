<?php

include('../../../inc/includes.php');

Session::checkRight("config", UPDATE);

if (!empty($_POST["test_mattermost_send"])) {
    PluginMattermostNotificationMattermostSetting::testNotification();
    Html::back();
} elseif (!empty($_POST["update"])) {
    GlpiPlugin\Mattermost\Config::saveConfig([
        'server_url' => $_POST['server_url'] ?? '',
        'bot_token'  => $_POST['bot_token'] ?? '',
        'debug_mode' => isset($_POST['debug_mode']) && $_POST['debug_mode'] == '1' ? 1 : 0,
    ]);

    Session::addMessageAfterRedirect(
        __('config_saved', 'mattermost'),
        false,
        INFO
    );

    Html::back();
}

Html::header(
    PluginMattermostNotificationMattermostSetting::getTypeName(),
    $_SERVER['PHP_SELF'],
    "config",
    "notification"
);

$setting = new PluginMattermostNotificationMattermostSetting();
$setting->display(['id' => 1]);

Html::footer();