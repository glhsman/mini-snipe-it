-- Idempotente Nachmigration fuer bestehende Installationen
-- Zweck: fehlende Spalte categories.kuerzel ergaenzen und Daten angleichen
-- Getestet fuer MySQL/MariaDB-kompatible Syntax

SET @schema_name := DATABASE();

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
    ADD COLUMN IF NOT EXISTS has_sim_fields BOOLEAN DEFAULT 0,
    ADD COLUMN IF NOT EXISTS has_hardware_fields BOOLEAN DEFAULT 0;

ALTER TABLE assets 
    ADD COLUMN IF NOT EXISTS pin VARCHAR(4) NULL,
    ADD COLUMN IF NOT EXISTS puk VARCHAR(8) NULL,
    ADD COLUMN IF NOT EXISTS rufnummer VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS mac_adresse VARCHAR(17) NULL,
    ADD COLUMN IF NOT EXISTS ram INT NULL,
    ADD COLUMN IF NOT EXISTS ssd_size INT NULL,
    ADD COLUMN IF NOT EXISTS cores INT NULL,
    ADD COLUMN IF NOT EXISTS os_version VARCHAR(100) NULL;

-- 12) Status labels erweitern (Defekt & Ausgegeben)
INSERT INTO status_labels (name, status_type)
SELECT 'Defekt', 'undeployable' FROM (SELECT 1) AS tmp
WHERE NOT EXISTS (SELECT name FROM status_labels WHERE name = 'Defekt');

INSERT INTO status_labels (name, status_type)
SELECT 'Ausgegeben', 'deployable' FROM (SELECT 1) AS tmp
WHERE NOT EXISTS (SELECT name FROM status_labels WHERE name = 'Ausgegeben');
