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

    private function envLine(string $key, $value): string {
        $value = (string) ($value ?? '');
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);
        return $key . "=" . $value . "\n";
    }

    public function saveConfig($data) {
        $content = "# Datenbank-Konfiguration\n";
        $content .= $this->envLine('DB_HOST', $data['db_host'] ?? '');
        $content .= $this->envLine('DB_NAME', $data['db_name'] ?? '');
        $content .= $this->envLine('DB_USER', $data['db_user'] ?? '');
        $content .= $this->envLine('DB_PASS', $data['db_pass'] ?? '');
        $content .= "\n";
        $content .= "# App-Einstellungen\n";
        $content .= $this->envLine('APP_NAME', 'Mini-Snipe');
        $content .= $this->envLine('APP_ENV', 'local');
        $content .= $this->envLine('APP_DEBUG', 'true');
        $content .= "\n";
        $content .= "# Mail-Konfiguration\n";
        $content .= $this->envLine('MAIL_HOST', $data['mail_host'] ?? '');
        $content .= $this->envLine('MAIL_PORT', $data['mail_port'] ?? '');
        $content .= $this->envLine('MAIL_ENCRYPTION', $data['mail_encryption'] ?? 'tls');
        $content .= $this->envLine('MAIL_USER', $data['mail_user'] ?? '');
        $content .= $this->envLine('MAIL_PASS', $data['mail_pass'] ?? '');
        $content .= $this->envLine('MAIL_FROM_ADDRESS', $data['mail_from_address'] ?? '');
        $content .= $this->envLine('MAIL_FROM_NAME', $data['mail_from_name'] ?? '');

        return file_put_contents($this->dbPath, $content);
    }

    public function testConnection($data) {
        try {
            if (!extension_loaded('pdo_mysql')) {
                throw new Exception("Der PHP-Treiber 'pdo_mysql' ist auf diesem System nicht installiert oder nicht aktiviert.");
            }

            $dsn = "mysql:host=" . $data['db_host'] . ";dbname=" . $data['db_name'] . ";charset=utf8mb4";
            new PDO($dsn, $data['db_user'], $data['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function runMigrations($data) {
        try {
            if (!extension_loaded('pdo_mysql')) {
                throw new Exception("Der PHP-Treiber 'pdo_mysql' ist auf diesem System nicht installiert oder nicht aktiviert.");
            }

            if (!is_file($this->sqlPath)) {
                throw new Exception("Die Datei 'database.sql' wurde im Projekt-Root nicht gefunden. Bitte diese Datei zusammen mit 'config', 'public', 'src' und 'vendor' auf das Zielsystem kopieren.");
            }
            if (!is_readable($this->sqlPath)) {
                throw new Exception("Die Datei 'database.sql' ist vorhanden, aber nicht lesbar. Bitte Dateirechte und Besitzer pruefen.");
            }

            $dsn = "mysql:host=" . $data['db_host'] . ";dbname=" . $data['db_name'] . ";charset=utf8mb4";
            $pdo = new PDO($dsn, $data['db_user'], $data['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            $sql = file_get_contents($this->sqlPath);
            if ($sql === false) {
                throw new Exception("Die Datei 'database.sql' konnte nicht geladen werden.");
            }

            // Statements einzeln ausführen – PDO::exec() ist bei Multi-Statement-Strings
            // nicht in allen MySQL/PDO-Konfigurationen zuverlässig.
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => $s !== ''
            );
            foreach ($statements as $statement) {
                $pdo->exec($statement);
            }
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
