<?php

include('../../../inc/includes.php');

Session::checkRight("config", UPDATE);

if (!empty($_POST["test_telegram_send"])) {
    PluginTelegramNotificationTelegramSetting::testNotification();
    Html::back();
} elseif (!empty($_POST["update"])) {
    GlpiPlugin\Telegram\Config::saveConfig([
        'bot_token'  => $_POST['bot_token'] ?? '',
        'debug_mode' => isset($_POST['debug_mode']) && $_POST['debug_mode'] == '1' ? 1 : 0,
    ]);

    Session::addMessageAfterRedirect(
        __('config_saved', 'telegram'),
        false,
        INFO
    );

    Html::back();
}

Html::header(
    PluginTelegramNotificationTelegramSetting::getTypeName(),
    $_SERVER['PHP_SELF'],
    "config",
    "notification"
);

$setting = new PluginTelegramNotificationTelegramSetting();
$setting->display(['id' => 1]);

Html::footer();