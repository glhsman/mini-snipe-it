<?php

namespace App\Helpers;

class Settings {
    private static $settings = null;

    public static function load($db) {
        if (null === self::$settings) {
            try {
                $result = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
                self::$settings = $result ?: [];
            } catch (\Throwable $e) {
                self::$settings = [];
            }
        }
        return self::$settings;
    }

    public static function get($key, $default = null) {
        return self::$settings[$key] ?? $default;
    }

    public static function getSiteName() {
        return self::$settings['site_name'] ?? 'Mini-Snipe';
    }

    public static function getPageTitle($pageTitle) {
        $siteName = self::getSiteName();
        if (empty($pageTitle)) {
            return $siteName;
        }
        return "{$pageTitle} - {$siteName}";
    }
}
