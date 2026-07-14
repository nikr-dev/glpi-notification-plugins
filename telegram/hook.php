<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

include_once __DIR__ . '/setup.php';

function plugin_telegram_install()
{
    global $DB;

    // Включаем режим уведомлений Telegram
    \Config::setConfigurationValues('core', ['notifications_telegram' => 0]);

    // Создаём таблицу конфигурации
    if (!$DB->tableExists('glpi_plugin_telegram_configs')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_telegram_configs` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `bot_token` VARCHAR(255) NOT NULL DEFAULT '',
            `is_active` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `debug_mode` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `date_creation` TIMESTAMP NULL DEFAULT NULL,
            `date_mod` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    // Вставляем дефолтную запись
    $iterator = $DB->request(['FROM' => 'glpi_plugin_telegram_configs', 'LIMIT' => 1]);
    if (count($iterator) === 0) {
        $DB->insert('glpi_plugin_telegram_configs', [
            'bot_token'      => '',
            'is_active'      => 0,
            'debug_mode'     => 0,
            'date_creation'  => date('Y-m-d H:i:s'),
        ]);
    }

    // Добавляем поля для пользователей
    if (!$DB->fieldExists('glpi_users', 'telegram_id')) {
        $DB->doQuery("ALTER TABLE `glpi_users` ADD COLUMN `telegram_id` VARCHAR(100) DEFAULT ''");
    }
    
    if (!$DB->fieldExists('glpi_users', 'telegram_chat_id')) {
        $DB->doQuery("ALTER TABLE `glpi_users` ADD COLUMN `telegram_chat_id` VARCHAR(100) DEFAULT ''");
    }
    
    if (!$DB->fieldExists('glpi_users', 'telegram_thread_id')) {
        $DB->doQuery("ALTER TABLE `glpi_users` ADD COLUMN `telegram_thread_id` INT DEFAULT NULL");
    }
    
    // Удаляем устаревшее поле если есть
    if ($DB->fieldExists('glpi_users', 'telegram_enabled')) {
        $DB->doQuery("ALTER TABLE `glpi_users` DROP COLUMN `telegram_enabled`");
    }

    return true;
}

function plugin_telegram_uninstall()
{
    global $DB;

    \Config::deleteConfigurationValues('core', ['notifications_telegram']);
    
    if ($DB->tableExists('glpi_plugin_telegram_configs')) {
        $DB->doQuery("DROP TABLE `glpi_plugin_telegram_configs`");
    }

    if ($DB->fieldExists('glpi_users', 'telegram_id')) {
        $DB->doQuery("ALTER TABLE `glpi_users` DROP COLUMN `telegram_id`");
    }
    
    if ($DB->fieldExists('glpi_users', 'telegram_chat_id')) {
        $DB->doQuery("ALTER TABLE `glpi_users` DROP COLUMN `telegram_chat_id`");
    }
    
    if ($DB->fieldExists('glpi_users', 'telegram_thread_id')) {
        $DB->doQuery("ALTER TABLE `glpi_users` DROP COLUMN `telegram_thread_id`");
    }
    
    if ($DB->fieldExists('glpi_users', 'telegram_enabled')) {
        $DB->doQuery("ALTER TABLE `glpi_users` DROP COLUMN `telegram_enabled`");
    }

    return true;
}