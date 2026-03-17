<?php
namespace App\Controllers;

use PDO;
use Exception;

class SetupController {
    private $dbPath;
    private $sqlPath;

    public function __construct() {
        $this->dbPath = __DIR__ . '/../../.env';
        $this->sqlPath = __DIR__ . '/../../database.sql';
    }

    public function isInstalled() {
        return file_exists($this->dbPath);
    }

    public function saveConfig($data) {
        $content = "# Datenbank-Konfiguration\n";
        $content .= "DB_HOST=" . $data['db_host'] . "\n";
        $content .= "DB_NAME=" . $data['db_name'] . "\n";
        $content .= "DB_USER=" . $data['db_user'] . "\n";
        $content .= "DB_PASS=" . $data['db_pass'] . "\n\n";
        $content .= "# App-Einstellungen\n";
        $content .= "APP_NAME=Mini-Snipe\n";
        $content .= "APP_ENV=local\n";
        $content .= "APP_DEBUG=true\n";

        return file_put_contents($this->dbPath, $content);
    }

    public function testConnection($data) {
        try {
            $dsn = "mysql:host=" . $data['db_host'] . ";dbname=" . $data['db_name'] . ";charset=utf8mb4";
            new PDO($dsn, $data['db_user'], $data['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function runMigrations($data) {
        try {
            $dsn = "mysql:host=" . $data['db_host'] . ";dbname=" . $data['db_name'] . ";charset=utf8mb4";
            $pdo = new PDO($dsn, $data['db_user'], $data['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            $sql = file_get_contents($this->sqlPath);
            if ($sql === false) {
                throw new Exception("Migration-Datei nicht gefunden.");
            }

            $pdo->exec($sql);
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
