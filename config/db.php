<?php
require_once __DIR__ . '/../src/Helpers/Auth.php';
require_once __DIR__ . '/../src/Helpers/Settings.php';
\App\Helpers\Auth::startSession();

// Einfache Hilfsfunktion zum Laden der .env Datei
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}

// .env laden (falls vorhanden)
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    // Falls wir uns nicht schon im Setup befinden, leite weiter
    if (basename($_SERVER['PHP_SELF']) !== 'setup.php') {
        header('Location: setup.php');
        exit;
    }
} else {
    loadEnv($envPath);
}

// Datenbank-Konfiguration mit Fallback auf Umgebungsvariablen oder Defaults
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'asset_management');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Auto-Migration für Settings Tabelle
            $stmt = $this->connection->query("SHOW TABLES LIKE 'settings'");
            if ($stmt->rowCount() == 0) {
                $this->connection->exec("CREATE TABLE settings (
                    id INT PRIMARY KEY,
                    site_name VARCHAR(255) DEFAULT 'Mini-Snipe',
                    branding_type VARCHAR(20) DEFAULT 'text',
                    site_logo VARCHAR(255) DEFAULT NULL,
                    site_favicon VARCHAR(255) DEFAULT NULL,
                    company_address TEXT DEFAULT NULL,
                    protocol_header_text TEXT DEFAULT NULL,
                    protocol_footer_text TEXT DEFAULT NULL,
                    mail_test_success_at DATETIME NULL,
                    mail_test_recipient VARCHAR(255) NULL,
                    mail_test_last_error TEXT NULL
                )");
                $this->connection->exec("INSERT INTO settings (id, site_name, branding_type, company_address, protocol_header_text, protocol_footer_text) VALUES (1, 'Mini-Snipe', 'text', 'Firmenadresse hier hinterlegen', 'Die unten aufgefuehrte IT-Hardware wird hiermit bestaetigt. Mit Ihrer Unterschrift bestaetigen Sie den ordnungsgemaessen Erhalt bzw. die vollstaendige Rueckgabe der aufgelisteten Geraete.', 'IT-Protokoll. Fuer Ihre/unsere Unterlagen.')");
            }

            $settingColumns = [
                'site_favicon'         => "ALTER TABLE settings ADD COLUMN site_favicon VARCHAR(255) NULL AFTER site_logo",
                'company_address'      => "ALTER TABLE settings ADD COLUMN company_address TEXT NULL AFTER site_favicon",
                'protocol_header_text' => "ALTER TABLE settings ADD COLUMN protocol_header_text TEXT NULL AFTER company_address",
                'protocol_footer_text' => "ALTER TABLE settings ADD COLUMN protocol_footer_text TEXT NULL AFTER protocol_header_text",
                'mail_test_success_at' => "ALTER TABLE settings ADD COLUMN mail_test_success_at DATETIME NULL AFTER protocol_footer_text",
                'mail_test_recipient'  => "ALTER TABLE settings ADD COLUMN mail_test_recipient VARCHAR(255) NULL AFTER mail_test_success_at",
                'mail_test_last_error' => "ALTER TABLE settings ADD COLUMN mail_test_last_error TEXT NULL AFTER mail_test_recipient",
            ];
            foreach ($settingColumns as $column => $sql) {
                $columnStmt = $this->connection->prepare("SELECT COUNT(*)
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'settings'
                      AND COLUMN_NAME = ?");
                $columnStmt->execute([$column]);
                if ((int) $columnStmt->fetchColumn() === 0) {
                    $this->connection->exec($sql);
                }
            }

            $this->connection->exec("UPDATE settings
                SET company_address = COALESCE(NULLIF(company_address, ''), 'Firmenadresse hier hinterlegen'),
                    protocol_header_text = COALESCE(NULLIF(protocol_header_text, ''), 'Die unten aufgefuehrte IT-Hardware wird hiermit bestaetigt. Mit Ihrer Unterschrift bestaetigen Sie den ordnungsgemaessen Erhalt bzw. die vollstaendige Rueckgabe der aufgelisteten Geraete.'),
                    protocol_footer_text = COALESCE(NULLIF(protocol_footer_text, ''), 'IT-Protokoll. Fuer Ihre/unsere Unterlagen.')
                WHERE id = 1");

            $this->connection->exec("CREATE TABLE IF NOT EXISTS asset_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                asset_id INT NOT NULL,
                user_id INT NOT NULL,
                checkout_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                checkout_by_user_id INT NULL,
                checkin_at DATETIME NULL,
                checkin_by_user_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
                FOREIGN KEY (checkout_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (checkin_by_user_id) REFERENCES users(id) ON DELETE SET NULL
            )");

            $this->connection->exec("CREATE TABLE IF NOT EXISTS login_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                username VARCHAR(100) NOT NULL,
                action ENUM('login','logout','login_failed','login_blocked') NOT NULL,
                reason VARCHAR(100) NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_login_logs_created (created_at),
                INDEX idx_login_logs_user (user_id)
            )");

            $this->connection->exec("CREATE TABLE IF NOT EXISTS asset_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                location_id INT NOT NULL,
                category_id INT NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                reason TEXT NOT NULL,
                status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                processed_at DATETIME NULL,
                processed_by_user_id INT NULL,
                internal_note TEXT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
                FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE RESTRICT,
                FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
                FOREIGN KEY (processed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_asset_requests_status_requested (status, requested_at),
                INDEX idx_asset_requests_user (user_id),
                INDEX idx_asset_requests_location (location_id),
                INDEX idx_asset_requests_category (category_id)
            )");

            $this->connection->exec("CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                requested_ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_password_resets_user (user_id),
                INDEX idx_password_resets_expires (expires_at)
            )");

            $assetArchivBitStmt = $this->connection->prepare("SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'assets'
                  AND COLUMN_NAME = 'archiv_bit'");
            $assetArchivBitStmt->execute();
            if ((int) $assetArchivBitStmt->fetchColumn() === 0) {
                $this->connection->exec("ALTER TABLE assets ADD COLUMN archiv_bit TINYINT(1) NOT NULL DEFAULT 0 AFTER os_version");
            }
            $this->connection->exec("UPDATE assets SET archiv_bit = 0 WHERE archiv_bit IS NULL");

            $this->connection->exec("ALTER TABLE assets ADD COLUMN IF NOT EXISTS room VARCHAR(255) NULL");
            $this->connection->exec("ALTER TABLE assets ADD COLUMN IF NOT EXISTS last_inventur DATETIME NULL");

            $loginReasonStmt = $this->connection->prepare("SELECT COUNT(*)
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'login_logs'
                  AND COLUMN_NAME = 'reason'");
            $loginReasonStmt->execute();
            if ((int) $loginReasonStmt->fetchColumn() === 0) {
                $this->connection->exec("ALTER TABLE login_logs ADD COLUMN reason VARCHAR(100) NULL AFTER action");
            }

            $loginActionTypeStmt = $this->connection->prepare("SELECT COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'login_logs'
                  AND COLUMN_NAME = 'action'");
            $loginActionTypeStmt->execute();
            $loginActionType = (string) $loginActionTypeStmt->fetchColumn();
            if (strpos($loginActionType, 'login_failed') === false || strpos($loginActionType, 'login_blocked') === false) {
                $this->connection->exec("ALTER TABLE login_logs MODIFY COLUMN action ENUM('login','logout','login_failed','login_blocked') NOT NULL");
            }
        } catch (PDOException $e) {
            die("Verbindungsfehler: " . $e->getMessage());
        }
    }

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance->getConnection();
    }

    public function getConnection() {
        return $this->connection;
    }
}
