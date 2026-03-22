-- Idempotente Nachmigration fuer bestehende Installationen
-- Zweck: fehlende Spalte categories.kuerzel ergaenzen und Daten angleichen
-- Getestet fuer MySQL/MariaDB-kompatible Syntax

SET @schema_name := DATABASE();

-- 0) settings-Tabelle anlegen, falls sie fehlt
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY,
    site_name VARCHAR(255) DEFAULT 'Mini-Snipe',
    branding_type VARCHAR(20) DEFAULT 'text',
    site_logo VARCHAR(255) DEFAULT NULL,
    site_favicon VARCHAR(255) DEFAULT NULL,
    company_address TEXT DEFAULT NULL,
    protocol_header_text TEXT DEFAULT NULL,
    protocol_footer_text TEXT DEFAULT NULL
);

INSERT INTO settings (id, site_name, branding_type)
SELECT 1,
       'Mini-Snipe',
       'text'
WHERE NOT EXISTS (
    SELECT 1 FROM settings WHERE id = 1
);

-- 0a) settings.site_favicon nur anlegen, falls sie fehlt
SET @has_site_favicon_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'site_favicon'
);

SET @sql_add_site_favicon_col := IF(
    @has_site_favicon_col = 0,
    'ALTER TABLE settings ADD COLUMN site_favicon VARCHAR(255) NULL AFTER site_logo',
    'SELECT ''Spalte settings.site_favicon existiert bereits'' AS info'
);

PREPARE stmt_add_site_favicon_col FROM @sql_add_site_favicon_col;
EXECUTE stmt_add_site_favicon_col;
DEALLOCATE PREPARE stmt_add_site_favicon_col;

SET @has_company_address_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'company_address'
);

SET @sql_add_company_address_col := IF(
    @has_company_address_col = 0,
    'ALTER TABLE settings ADD COLUMN company_address TEXT NULL AFTER site_favicon',
    'SELECT ''Spalte settings.company_address existiert bereits'' AS info'
);

PREPARE stmt_add_company_address_col FROM @sql_add_company_address_col;
EXECUTE stmt_add_company_address_col;
DEALLOCATE PREPARE stmt_add_company_address_col;

SET @has_protocol_header_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'protocol_header_text'
);

SET @sql_add_protocol_header_col := IF(
    @has_protocol_header_col = 0,
    'ALTER TABLE settings ADD COLUMN protocol_header_text TEXT NULL AFTER company_address',
    'SELECT ''Spalte settings.protocol_header_text existiert bereits'' AS info'
);

PREPARE stmt_add_protocol_header_col FROM @sql_add_protocol_header_col;
EXECUTE stmt_add_protocol_header_col;
DEALLOCATE PREPARE stmt_add_protocol_header_col;

SET @has_protocol_footer_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'protocol_footer_text'
);

SET @sql_add_protocol_footer_col := IF(
    @has_protocol_footer_col = 0,
    'ALTER TABLE settings ADD COLUMN protocol_footer_text TEXT NULL AFTER protocol_header_text',
    'SELECT ''Spalte settings.protocol_footer_text existiert bereits'' AS info'
);

PREPARE stmt_add_protocol_footer_col FROM @sql_add_protocol_footer_col;
EXECUTE stmt_add_protocol_footer_col;
DEALLOCATE PREPARE stmt_add_protocol_footer_col;

SET @has_mail_test_success_at_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'mail_test_success_at'
);

SET @sql_add_mail_test_success_at_col := IF(
    @has_mail_test_success_at_col = 0,
    'ALTER TABLE settings ADD COLUMN mail_test_success_at DATETIME NULL AFTER protocol_footer_text',
    'SELECT ''Spalte settings.mail_test_success_at existiert bereits'' AS info'
);

PREPARE stmt_add_mail_test_success_at_col FROM @sql_add_mail_test_success_at_col;
EXECUTE stmt_add_mail_test_success_at_col;
DEALLOCATE PREPARE stmt_add_mail_test_success_at_col;

SET @has_mail_test_recipient_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'mail_test_recipient'
);

SET @sql_add_mail_test_recipient_col := IF(
    @has_mail_test_recipient_col = 0,
    'ALTER TABLE settings ADD COLUMN mail_test_recipient VARCHAR(255) NULL AFTER mail_test_success_at',
    'SELECT ''Spalte settings.mail_test_recipient existiert bereits'' AS info'
);

PREPARE stmt_add_mail_test_recipient_col FROM @sql_add_mail_test_recipient_col;
EXECUTE stmt_add_mail_test_recipient_col;
DEALLOCATE PREPARE stmt_add_mail_test_recipient_col;

SET @has_mail_test_last_error_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'settings'
      AND COLUMN_NAME = 'mail_test_last_error'
);

SET @sql_add_mail_test_last_error_col := IF(
    @has_mail_test_last_error_col = 0,
    'ALTER TABLE settings ADD COLUMN mail_test_last_error TEXT NULL AFTER mail_test_recipient',
    'SELECT ''Spalte settings.mail_test_last_error existiert bereits'' AS info'
);

PREPARE stmt_add_mail_test_last_error_col FROM @sql_add_mail_test_last_error_col;
EXECUTE stmt_add_mail_test_last_error_col;
DEALLOCATE PREPARE stmt_add_mail_test_last_error_col;

UPDATE settings
SET company_address = COALESCE(NULLIF(company_address, ''), 'Firmenadresse hier hinterlegen'),
    protocol_header_text = COALESCE(NULLIF(protocol_header_text, ''), 'Die unten aufgefuehrte IT-Hardware wird hiermit bestaetigt. Mit Ihrer Unterschrift bestaetigen Sie den ordnungsgemaessen Erhalt bzw. die vollstaendige Rueckgabe der aufgelisteten Geraete.'),
    protocol_footer_text = COALESCE(NULLIF(protocol_footer_text, ''), 'IT-Protokoll. Fuer Ihre/unsere Unterlagen.')
WHERE id = 1;

-- 0b) Historie fuer Asset-Ausgaben/Rueckgaben
CREATE TABLE IF NOT EXISTS asset_assignments (
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
);

-- 1) Spalte categories.kuerzel nur anlegen, falls sie fehlt
SET @has_kuerzel_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'categories'
      AND COLUMN_NAME = 'kuerzel'
);

SET @sql_add_col := IF(
    @has_kuerzel_col = 0,
    'ALTER TABLE categories ADD COLUMN kuerzel VARCHAR(2) NULL AFTER name',
    'SELECT ''Spalte categories.kuerzel existiert bereits'' AS info'
);

PREPARE stmt_add_col FROM @sql_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

-- 2) Fehlende/Leere Kuerzel aus dem Namen ableiten
UPDATE categories
SET kuerzel = UPPER(LEFT(TRIM(name), 2))
WHERE (kuerzel IS NULL OR kuerzel = '')
  AND name IS NOT NULL
  AND TRIM(name) <> '';

-- 3) Optionaler Unique-Index nur wenn
--    a) noch nicht vorhanden
--    b) keine doppelten Kuerzel existieren
SET @has_uq_kuerzel := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'categories'
      AND INDEX_NAME = 'uq_categories_kuerzel'
);

SET @has_duplicate_kuerzel := (
    SELECT COUNT(*)
    FROM (
        SELECT kuerzel
        FROM categories
        WHERE kuerzel IS NOT NULL
          AND kuerzel <> ''
        GROUP BY kuerzel
        HAVING COUNT(*) > 1
    ) AS dups
);

SET @sql_add_uq := IF(
    @has_uq_kuerzel > 0,
    'SELECT ''Index uq_categories_kuerzel existiert bereits'' AS info',
    IF(
        @has_duplicate_kuerzel = 0,
        'ALTER TABLE categories ADD CONSTRAINT uq_categories_kuerzel UNIQUE (kuerzel)',
        'SELECT ''Unique-Index uebersprungen: doppelte kuerzel vorhanden'' AS info'
    )
);

PREPARE stmt_add_uq FROM @sql_add_uq;
EXECUTE stmt_add_uq;
DEALLOCATE PREPARE stmt_add_uq;

-- 4) locations.kuerzel nur anlegen, falls sie fehlt
SET @has_loc_kuerzel_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'locations'
      AND COLUMN_NAME = 'kuerzel'
);

SET @sql_add_loc_kuerzel := IF(
    @has_loc_kuerzel_col = 0,
    'ALTER TABLE locations ADD COLUMN kuerzel VARCHAR(2) NULL AFTER city',
    'SELECT ''Spalte locations.kuerzel existiert bereits'' AS info'
);

PREPARE stmt_add_loc_kuerzel FROM @sql_add_loc_kuerzel;
EXECUTE stmt_add_loc_kuerzel;
DEALLOCATE PREPARE stmt_add_loc_kuerzel;

-- 5) users.can_login nur anlegen, falls die Spalte fehlt
SET @has_can_login_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'can_login'
);

SET @sql_add_can_login_col := IF(
    @has_can_login_col = 0,
    'ALTER TABLE users ADD COLUMN can_login TINYINT(1) NOT NULL DEFAULT 1 AFTER password',
    'SELECT ''Spalte users.can_login existiert bereits'' AS info'
);

PREPARE stmt_add_can_login_col FROM @sql_add_can_login_col;
EXECUTE stmt_add_can_login_col;
DEALLOCATE PREPARE stmt_add_can_login_col;

-- 6) users.password auf NULL erlauben um Benutzer ohne Web-Login zu unterstuetzen
SET @users_password_nullable := (
    SELECT IS_NULLABLE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'password'
    LIMIT 1
);

SET @sql_password_nullable := IF(
    @users_password_nullable = 'NO',
    'ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL',
    'SELECT ''Spalte users.password ist bereits nullable'' AS info'
);

PREPARE stmt_password_nullable FROM @sql_password_nullable;
EXECUTE stmt_password_nullable;
DEALLOCATE PREPARE stmt_password_nullable;

-- 7) Konsistenz herstellen: ohne Passwort kein Web-Login
UPDATE users
SET can_login = 0
WHERE (password IS NULL OR password = '')
  AND can_login = 1;

-- 8) users.email Unique-Constraint entfernen um Sammelpostfaecher zu erlauben
SET @has_uq_email := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'users'
      AND INDEX_NAME = 'email'
);

SET @sql_drop_uq_email := IF(
    @has_uq_email > 0,
    'ALTER TABLE users DROP INDEX email',
    'SELECT ''Unique-Index email existiert nicht oder wurde bereits geloescht'' AS info'
);

PREPARE stmt_drop_uq_email FROM @sql_drop_uq_email;
EXECUTE stmt_drop_uq_email;
DEALLOCATE PREPARE stmt_drop_uq_email;

-- 9) assets.serial Unique-Constraint hinzufügen (nach Bereinigung von leeren Werten)
UPDATE assets SET serial = NULL WHERE TRIM(serial) = '';

SET @has_uq_serial := (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
      AND TABLE_NAME = 'assets' 
      AND INDEX_NAME = 'uq_assets_serial'
);

SET @sql_add_uq_serial := IF(
    @has_uq_serial = 0,
    'ALTER TABLE assets ADD CONSTRAINT uq_assets_serial UNIQUE (serial)',
    'SELECT ''Unique-Index uq_assets_serial existiert bereits'' AS info'
);

PREPARE stmt_add_uq_serial FROM @sql_add_uq_serial;
EXECUTE stmt_add_uq_serial;
DEALLOCATE PREPARE stmt_add_uq_serial;

-- 10) assets.asset_tag auf NULL erlauben (für Verbrauchsmaterialien)
ALTER TABLE assets MODIFY COLUMN asset_tag VARCHAR(100) NULL;

-- 11) Zusatzfelder für SIM und Hardware
ALTER TABLE asset_models 
    ADD COLUMN IF NOT EXISTS serial_number_required BOOLEAN DEFAULT 1,
    ADD COLUMN IF NOT EXISTS has_sim_fields BOOLEAN DEFAULT 0,
    ADD COLUMN IF NOT EXISTS has_hardware_fields BOOLEAN DEFAULT 0;

ALTER TABLE assets 
    ADD COLUMN IF NOT EXISTS serial_number_required TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS room VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS pin VARCHAR(4) NULL,
    ADD COLUMN IF NOT EXISTS puk VARCHAR(8) NULL,
    ADD COLUMN IF NOT EXISTS rufnummer VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS mac_adresse VARCHAR(17) NULL,
    ADD COLUMN IF NOT EXISTS ram INT NULL,
    ADD COLUMN IF NOT EXISTS ssd_size INT NULL,
    ADD COLUMN IF NOT EXISTS cores INT NULL,
    ADD COLUMN IF NOT EXISTS os_version VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS last_inventur DATETIME NULL;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS personalnummer VARCHAR(10) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS vorgesetzter VARCHAR(100) NULL AFTER personalnummer,
    ADD COLUMN IF NOT EXISTS is_activ TINYINT(1) NOT NULL DEFAULT 1 AFTER vorgesetzter;

-- 12) Status labels erweitern (Defekt & Ausgegeben)
INSERT INTO status_labels (name, status_type)
SELECT 'Defekt', 'undeployable' FROM (SELECT 1) AS tmp
WHERE NOT EXISTS (SELECT name FROM status_labels WHERE name = 'Defekt');

INSERT INTO status_labels (name, status_type)
SELECT 'Ausgegeben', 'deployable' FROM (SELECT 1) AS tmp
WHERE NOT EXISTS (SELECT name FROM status_labels WHERE name = 'Ausgegeben');

-- 13) Lookup-Tabellen für Hardware erstellen (Idempotent)
CREATE TABLE IF NOT EXISTS lookup_ram    (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(50)  UNIQUE);
CREATE TABLE IF NOT EXISTS lookup_ssd    (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(50)  UNIQUE);
CREATE TABLE IF NOT EXISTS lookup_cores  (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(50)  UNIQUE);
CREATE TABLE IF NOT EXISTS lookup_os     (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(100) UNIQUE);

-- 14) Login-Protokoll-Tabelle anlegen (Idempotent)
CREATE TABLE IF NOT EXISTS login_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT  NULL,
    username    VARCHAR(100) NOT NULL,
    action      ENUM('login','logout','login_failed','login_blocked') NOT NULL,
    reason      VARCHAR(100) NULL,
    ip_address  VARCHAR(45)  NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_logs_created (created_at),
    INDEX idx_login_logs_user    (user_id)
);

-- 14c) Inventurdaten-Puffer fuer manuelle Pruefung im Asset Management
-- Wird von `public/api/mobile/v1/sync/inventory.php` beschrieben und von
-- `public/inventory_review.php` / `public/inventory_review_detail.php` verarbeitet.
CREATE TABLE IF NOT EXISTS inventory_staging (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(120) NOT NULL,
    serial_number VARCHAR(255) NOT NULL,
    asset_model_id INT NULL,
    room_text VARCHAR(255) NULL,
    comment_text TEXT NULL,
    company_id INT NULL,
    company_name VARCHAR(255) NULL,
    captured_at DATETIME NULL,
    sync_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    review_note TEXT NULL,
    reviewed_at DATETIME NULL,
    reviewed_by_user_id INT NULL,
    target_asset_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inventory_staging_client_id (client_id),
    INDEX idx_inventory_staging_status (sync_status),
    INDEX idx_inventory_staging_serial (serial_number),
    INDEX idx_inventory_staging_company (company_id),
    INDEX idx_inventory_staging_asset_model (asset_model_id),
    INDEX idx_inventory_staging_reviewed_by (reviewed_by_user_id),
    INDEX idx_inventory_staging_target_asset (target_asset_id)
);

ALTER TABLE inventory_staging
    ADD COLUMN IF NOT EXISTS room_text VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS comment_text TEXT NULL;

-- Rueckwaertskompatibel: alte Spalte location_text in room_text ueberfuehren
SET @has_inventory_location_text_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'inventory_staging'
      AND COLUMN_NAME = 'location_text'
);

SET @sql_copy_inventory_location_to_room := IF(
    @has_inventory_location_text_col > 0,
    'UPDATE inventory_staging SET room_text = COALESCE(NULLIF(room_text, ''''), location_text) WHERE room_text IS NULL OR room_text = ''''',
    'SELECT ''Spalte inventory_staging.location_text nicht vorhanden'' AS info'
);

PREPARE stmt_copy_inventory_location_to_room FROM @sql_copy_inventory_location_to_room;
EXECUTE stmt_copy_inventory_location_to_room;
DEALLOCATE PREPARE stmt_copy_inventory_location_to_room;

-- 14b) login_logs um neue Felder/Aktionen erweitern (idempotent)
SET @has_login_reason_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'login_logs'
      AND COLUMN_NAME = 'reason'
);

SET @sql_add_login_reason_col := IF(
    @has_login_reason_col = 0,
    'ALTER TABLE login_logs ADD COLUMN reason VARCHAR(100) NULL AFTER action',
    'SELECT ''Spalte login_logs.reason existiert bereits'' AS info'
);

PREPARE stmt_add_login_reason_col FROM @sql_add_login_reason_col;
EXECUTE stmt_add_login_reason_col;
DEALLOCATE PREPARE stmt_add_login_reason_col;

SET @login_action_type := (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'login_logs'
      AND COLUMN_NAME = 'action'
    LIMIT 1
);

SET @sql_expand_login_action_enum := IF(
    @login_action_type NOT LIKE '%login_failed%' OR @login_action_type NOT LIKE '%login_blocked%',
    'ALTER TABLE login_logs MODIFY COLUMN action ENUM(''login'',''logout'',''login_failed'',''login_blocked'') NOT NULL',
    'SELECT ''login_logs.action enthält bereits alle Login-Events'' AS info'
);

PREPARE stmt_expand_login_action_enum FROM @sql_expand_login_action_enum;
EXECUTE stmt_expand_login_action_enum;
DEALLOCATE PREPARE stmt_expand_login_action_enum;

-- 14) Standardwerte für Lookups
INSERT IGNORE INTO lookup_ram (value) VALUES ('4 GB'), ('8 GB'), ('16 GB'), ('32 GB'), ('64 GB');
INSERT IGNORE INTO lookup_ssd (value) VALUES ('128 GB'), ('256 GB'), ('512 GB'), ('1 TB'), ('2 TB');
INSERT IGNORE INTO lookup_cores (value) VALUES ('2'), ('4'), ('6'), ('8'), ('10'), ('12'), ('16');
INSERT IGNORE INTO lookup_os (value) VALUES ('Windows 10'), ('Windows 11'), ('macOS'), ('Linux'), ('Android'), ('iOS');

-- 15) Migriere bestehende Daten aus Assets (idempotent)
INSERT IGNORE INTO lookup_ram (value) SELECT DISTINCT CONCAT(ram, ' GB') FROM assets WHERE ram IS NOT NULL AND ram > 0 AND ram NOT IN (SELECT id FROM lookup_ram);
INSERT IGNORE INTO lookup_ssd (value) SELECT DISTINCT CONCAT(ssd_size, ' GB') FROM assets WHERE ssd_size IS NOT NULL AND ssd_size > 0 AND ssd_size NOT IN (SELECT id FROM lookup_ssd);
INSERT IGNORE INTO lookup_cores (value) SELECT DISTINCT cores FROM assets WHERE cores IS NOT NULL AND cores > 0 AND cores NOT IN (SELECT id FROM lookup_cores);
INSERT IGNORE INTO lookup_os (value)
SELECT DISTINCT TRIM(os_version)
FROM assets
WHERE os_version IS NOT NULL
    AND TRIM(os_version) <> ''
    AND TRIM(os_version) NOT REGEXP '^[0-9]+$';

-- 16) Assets Tabelle updaten (IDs statt Rohwerte)
UPDATE assets a JOIN lookup_ram l ON CONCAT(a.ram, ' GB') = l.value SET a.ram = l.id WHERE a.ram IS NOT NULL AND a.ram > 0 AND a.ram NOT IN (SELECT id FROM lookup_ram);
UPDATE assets a JOIN lookup_ssd l ON CONCAT(a.ssd_size, ' GB') = l.value SET a.ssd_size = l.id WHERE a.ssd_size IS NOT NULL AND a.ssd_size > 0 AND a.ssd_size NOT IN (SELECT id FROM lookup_ssd);
UPDATE assets a JOIN lookup_cores l ON a.cores = l.value SET a.cores = l.id WHERE a.cores IS NOT NULL AND a.cores > 0 AND a.cores NOT IN (SELECT id FROM lookup_cores);
UPDATE assets a
JOIN lookup_os l ON TRIM(a.os_version) = l.value
SET a.os_version = l.id
WHERE a.os_version IS NOT NULL
    AND TRIM(a.os_version) <> ''
    AND TRIM(a.os_version) NOT REGEXP '^[0-9]+$';

-- 17) assets.os_version auf Lookup-ID-Typ umstellen
SET @os_version_data_type := (
        SELECT DATA_TYPE
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @schema_name
            AND TABLE_NAME = 'assets'
            AND COLUMN_NAME = 'os_version'
        LIMIT 1
);

SET @sql_set_os_version_int := IF(
        @os_version_data_type <> 'int',
        'ALTER TABLE assets MODIFY COLUMN os_version INT NULL',
        'SELECT ''Spalte assets.os_version ist bereits INT'' AS info'
);

PREPARE stmt_set_os_version_int FROM @sql_set_os_version_int;
EXECUTE stmt_set_os_version_int;
DEALLOCATE PREPARE stmt_set_os_version_int;

-- 17b) assets.archiv_bit fuer Drittanbieter-Export (0=nicht exportiert, 1=exportiert)
SET @has_archiv_bit_col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'assets'
      AND COLUMN_NAME = 'archiv_bit'
);

SET @sql_add_archiv_bit_col := IF(
    @has_archiv_bit_col = 0,
    'ALTER TABLE assets ADD COLUMN archiv_bit TINYINT(1) NOT NULL DEFAULT 0 AFTER os_version',
    'SELECT ''Spalte assets.archiv_bit existiert bereits'' AS info'
);

PREPARE stmt_add_archiv_bit_col FROM @sql_add_archiv_bit_col;
EXECUTE stmt_add_archiv_bit_col;
DEALLOCATE PREPARE stmt_add_archiv_bit_col;

UPDATE assets
SET archiv_bit = 0
WHERE archiv_bit IS NULL;

-- 18) Asset-Anforderungen (oeffentlich) speichern
CREATE TABLE IF NOT EXISTS asset_requests (
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
);

CREATE TABLE IF NOT EXISTS password_resets (
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
);
