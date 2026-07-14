<?php

namespace GlpiPlugin\Mattermost;

use CommonDBTM;
use Session;

class Config extends CommonDBTM
{
    public static $rightname = 'config';

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_mattermost_configs';
    }

    public static function getTypeName($nb = 0)
    {
        return __('config', 'mattermost');
    }

    public static function canView(): bool
    {
        return Session::haveRight('config', READ);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function canCreate(): bool
    {
        return Session::haveRight('config', CREATE);
    }

    public static function getConfig()
    {
        global $DB;
        
        $iterator = $DB->request([
            'FROM'  => 'glpi_plugin_mattermost_configs',
            'LIMIT' => 1
        ]);
        
        $row = $iterator->current();
        
        if ($row) {
            return $row;
        }
        
        // Создаём запись по умолчанию если не существует
        $DB->insert('glpi_plugin_mattermost_configs', [
            'id'            => 1,
            'server_url'    => '',
            'bot_token'     => '',
            'is_active'     => 0,
            'debug_mode'    => 0,
            'date_creation' => date('Y-m-d H:i:s'),
        ]);
        
        return [
            'id'            => 1,
            'server_url'    => '',
            'bot_token'     => '',
            'is_active'     => 0,
            'debug_mode'    => 0,
            'date_creation' => date('Y-m-d H:i:s'),
        ];
    }
    
    public static function saveConfig(array $data)
    {
        global $DB;
        
        $serverUrl = rtrim(trim($data['server_url'] ?? ''), '/');
        $botToken = trim($data['bot_token'] ?? '');
        $debugMode = isset($data['debug_mode']) && $data['debug_mode'] ? 1 : 0;
        $isActive = (!empty($serverUrl) && !empty($botToken)) ? 1 : 0;
        
        $DB->update(
            'glpi_plugin_mattermost_configs',
            [
                'server_url' => $serverUrl,
                'bot_token'  => $botToken,
                'debug_mode' => $debugMode,
                'is_active'  => $isActive,
                'date_mod'   => date('Y-m-d H:i:s'),
            ],
            ['id' => 1]
        );
    }
    
    public static function isDebugMode(): bool
    {
        $config = self::getConfig();
        return !empty($config['debug_mode']);
    }
}
