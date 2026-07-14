<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

include_once __DIR__ . '/setup.php';

function plugin_mattermost_install()
{
    global $DB;

    // Включаем режим уведомлений Mattermost
    \Config::setConfigurationValues('core', ['notifications_mattermost' => 0]);

    // Создаём таблицу конфигурации
    if (!$DB->tableExists('glpi_plugin_mattermost_configs')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_mattermost_configs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `server_url` VARCHAR(255) NOT NULL DEFAULT '',
            `bot_token` VARCHAR(255) NOT NULL DEFAULT '',
            `is_active` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `debug_mode` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `date_creation` TIMESTAMP NULL DEFAULT NULL,
            `date_mod` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // Вставляем дефолтную запись
    $iterator = $DB->request(['FROM' => 'glpi_plugin_mattermost_configs', 'LIMIT' => 1]);
    if (count($iterator) === 0) {
        $DB->insert('glpi_plugin_mattermost_configs', [
            'server_url'     => '',
            'bot_token'      => '',
            'is_active'      => 0,
            'debug_mode'     => 0,
            'date_creation'  => date('Y-m-d H:i:s'),
        ]);
    }

    // Добавляем поля для пользователей
    if (!$DB->fieldExists('glpi_users', 'mattermost_id')) {
        $DB->doQuery("ALTER TABLE `glpi_users` ADD COLUMN `mattermost_id` VARCHAR(100) DEFAULT ''");
    }
    
    if (!$DB->fieldExists('glpi_users', 'mattermost_channel_id')) {
        $DB->doQuery("ALTER TABLE `glpi_users` ADD COLUMN `mattermost_channel_id` VARCHAR(100) DEFAULT ''");
    }
    
    // Удаляем устаревшее поле если есть
    if ($DB->fieldExists('glpi_users', 'mattermost_enabled')) {
        $DB->doQuery("ALTER TABLE `glpi_users` DROP COLUMN `mattermost_enabled`");
    }
    
    // Удаляем поле для групп если есть (уведомления группам не поддерживаются)
    if ($DB->fieldExists('glpi_groups', 'mattermost_id')) {
        $DB->doQuery("ALTER TABLE `glpi_groups` DROP COLUMN `mattermost_id`");
    }

    return true;
}

function plugin_mattermost_uninstall()
{
    global $DB;

    \Config::deleteConfigurationValues('core', ['notifications_mattermost']);
    
    if ($DB->tableExists('glpi_plugin_mattermost_configs')) {
        $DB->doQuery("DROP TABLE `glpi_plugin_mattermost_configs`");
    }

    if ($DB->fieldExists('glpi_users', 'mattermost_id')) {
        $DB->doQuery("ALTER TABLE `glpi_users` DROP COLUMN `mattermost_id`");
    }
    
    if ($DB->fieldExists('glpi_users', 'mattermost_channel_id')) {
        $DB->doQuery("ALTER TABLE `glpi_users` DROP COLUMN `mattermost_channel_id`");
    }
    
    if ($DB->fieldExists('glpi_users', 'mattermost_enabled')) {
        $DB->doQuery("ALTER TABLE `glpi_users` DROP COLUMN `mattermost_enabled`");
    }
    
    if ($DB->fieldExists('glpi_groups', 'mattermost_id')) {
        $DB->doQuery("ALTER TABLE `glpi_groups` DROP COLUMN `mattermost_id`");
    }

    return true;
}