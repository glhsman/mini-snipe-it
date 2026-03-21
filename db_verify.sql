-- Verifikation nach db_migration.sql
-- Fuehrt nur Lesebefehle aus (keine Aenderungen)

SET @schema_name := DATABASE();

SELECT 'Aktuelle Datenbank' AS check_name, @schema_name AS result;

-- 1) Tabellen vorhanden?
SELECT 'Tabelle users vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.TABLES
           WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'users'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Tabelle categories vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.TABLES
           WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'categories'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Tabelle settings vorhanden' AS check_name,
     IF(EXISTS(
       SELECT 1 FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'settings'
     ), 'OK', 'FEHLT') AS result;

SELECT 'Tabelle assets vorhanden' AS check_name,
     IF(EXISTS(
       SELECT 1 FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'assets'
     ), 'OK', 'FEHLT') AS result;

SELECT 'Tabelle asset_assignments vorhanden' AS check_name,
     IF(EXISTS(
       SELECT 1 FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'asset_assignments'
     ), 'OK', 'FEHLT') AS result;

SELECT 'Tabelle asset_requests vorhanden' AS check_name,
     IF(EXISTS(
       SELECT 1 FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'asset_requests'
     ), 'OK', 'FEHLT') AS result;

SELECT 'Tabelle password_resets vorhanden' AS check_name,
     IF(EXISTS(
       SELECT 1 FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'password_resets'
     ), 'OK', 'FEHLT') AS result;

-- 2) Kritische Spalten vorhanden?
SELECT 'Spalte categories.kuerzel vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'categories'
             AND COLUMN_NAME = 'kuerzel'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte locations.kuerzel vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'locations'
             AND COLUMN_NAME = 'kuerzel'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte users.role vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'role'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte users.location_id vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'location_id'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte users.can_login vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'can_login'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte users.personalnummer vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'personalnummer'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte users.vorgesetzter vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'vorgesetzter'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte users.is_activ vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'users'
             AND COLUMN_NAME = 'is_activ'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte settings.site_favicon vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'settings'
             AND COLUMN_NAME = 'site_favicon'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte settings.company_address vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'settings'
             AND COLUMN_NAME = 'company_address'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte settings.protocol_header_text vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'settings'
             AND COLUMN_NAME = 'protocol_header_text'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte settings.protocol_footer_text vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'settings'
             AND COLUMN_NAME = 'protocol_footer_text'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte settings.mail_test_success_at vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'settings'
             AND COLUMN_NAME = 'mail_test_success_at'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte settings.mail_test_recipient vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'settings'
             AND COLUMN_NAME = 'mail_test_recipient'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte settings.mail_test_last_error vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'settings'
             AND COLUMN_NAME = 'mail_test_last_error'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte asset_models.serial_number_required vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'asset_models'
             AND COLUMN_NAME = 'serial_number_required'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte asset_models.has_sim_fields vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'asset_models'
             AND COLUMN_NAME = 'has_sim_fields'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte asset_models.has_hardware_fields vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'asset_models'
             AND COLUMN_NAME = 'has_hardware_fields'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte assets.serial_number_required vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'assets'
             AND COLUMN_NAME = 'serial_number_required'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte assets.os_version ist INT' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'assets'
             AND COLUMN_NAME = 'os_version'
             AND DATA_TYPE = 'int'
       ), 'OK', 'FEHLT/FALSCHER TYP') AS result;

SELECT 'Spalte assets.archiv_bit vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'assets'
             AND COLUMN_NAME = 'archiv_bit'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte asset_requests.status vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'asset_requests'
             AND COLUMN_NAME = 'status'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte asset_requests.location_id vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'asset_requests'
             AND COLUMN_NAME = 'location_id'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte asset_requests.category_id vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'asset_requests'
             AND COLUMN_NAME = 'category_id'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte asset_requests.processed_by_user_id vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'asset_requests'
             AND COLUMN_NAME = 'processed_by_user_id'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte asset_requests.internal_note vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'asset_requests'
             AND COLUMN_NAME = 'internal_note'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte asset_assignments.checkout_by_user_id vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'asset_assignments'
             AND COLUMN_NAME = 'checkout_by_user_id'
       ), 'OK', 'FEHLT') AS result;

SELECT 'Spalte asset_assignments.checkin_by_user_id vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'asset_assignments'
             AND COLUMN_NAME = 'checkin_by_user_id'
       ), 'OK', 'FEHLT') AS result;

SET @has_users_can_login := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'can_login'
);

-- 3) Datenqualitaet Kategorien
SELECT 'Kategorien mit leerem/NULL kuerzel' AS check_name,
       COUNT(*) AS result
FROM categories
WHERE kuerzel IS NULL OR kuerzel = '';

SELECT 'Kategorien mit ungueltigem kuerzel (nicht 2 Buchstaben)' AS check_name,
       COUNT(*) AS result
FROM categories
WHERE kuerzel IS NOT NULL
  AND kuerzel <> ''
  AND kuerzel NOT REGEXP '^[A-Z]{2}$';

SELECT 'Doppelte Kategorien-Kuerzel' AS check_name,
       COUNT(*) AS result
FROM (
    SELECT kuerzel
    FROM categories
    WHERE kuerzel IS NOT NULL AND kuerzel <> ''
    GROUP BY kuerzel
    HAVING COUNT(*) > 1
) d;

-- 4) Indexstatus
SELECT 'Index uq_categories_kuerzel vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.STATISTICS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'categories'
             AND INDEX_NAME = 'uq_categories_kuerzel'
       ), 'OK', 'NICHT VORHANDEN') AS result;

-- 5) User-bezogene Plausibilitaet
SELECT 'Anzahl Benutzer gesamt' AS check_name,
       COUNT(*) AS result
FROM users;

SELECT 'Benutzer ohne username' AS check_name,
       COUNT(*) AS result
FROM users
WHERE username IS NULL OR TRIM(username) = '';

SELECT 'Benutzer mit ungueltiger Rolle' AS check_name,
       COUNT(*) AS result
FROM users
WHERE role NOT IN ('admin', 'editor', 'user') OR role IS NULL;

SET @sql_check_invalid_can_login := IF(
  @has_users_can_login > 0,
  'SELECT ''Benutzer mit ungueltigem can_login Wert'' AS check_name, COUNT(*) AS result FROM users WHERE can_login NOT IN (0,1) OR can_login IS NULL',
  'SELECT ''Benutzer mit ungueltigem can_login Wert'' AS check_name, ''SPALTE FEHLT'' AS result'
);
PREPARE stmt_check_invalid_can_login FROM @sql_check_invalid_can_login;
EXECUTE stmt_check_invalid_can_login;
DEALLOCATE PREPARE stmt_check_invalid_can_login;

SET @sql_check_missing_pw := IF(
  @has_users_can_login > 0,
  'SELECT ''Benutzer mit can_login=1 aber ohne Passwort'' AS check_name, COUNT(*) AS result FROM users WHERE can_login = 1 AND (password IS NULL OR password = '''')',
  'SELECT ''Benutzer mit can_login=1 aber ohne Passwort'' AS check_name, ''SPALTE FEHLT'' AS result'
);
PREPARE stmt_check_missing_pw FROM @sql_check_missing_pw;
EXECUTE stmt_check_missing_pw;
DEALLOCATE PREPARE stmt_check_missing_pw;

SELECT 'Benutzer mit nicht existierendem Standort' AS check_name,
       COUNT(*) AS result
FROM users u
LEFT JOIN locations l ON l.id = u.location_id
WHERE u.location_id IS NOT NULL
  AND l.id IS NULL;

-- 6) Asset-Zuordnungs-Historie (Plausibilitaet)
SELECT 'Offene Zuordnungen ohne aktuell zugewiesenes Asset' AS check_name,
       COUNT(*) AS result
FROM asset_assignments aa
LEFT JOIN assets a ON a.id = aa.asset_id
WHERE aa.checkin_at IS NULL
  AND (a.id IS NULL OR a.user_id IS NULL OR a.user_id <> aa.user_id);

SELECT 'Assets mit mehr als einer offenen Zuordnung' AS check_name,
       COUNT(*) AS result
FROM (
    SELECT asset_id
    FROM asset_assignments
    WHERE checkin_at IS NULL
    GROUP BY asset_id
    HAVING COUNT(*) > 1
) x;

SELECT 'Asset-Anforderungen mit ungueltigem Status' AS check_name,
     COUNT(*) AS result
FROM asset_requests
WHERE status NOT IN ('pending', 'approved', 'rejected')
  OR status IS NULL;

SELECT 'Asset-Anforderungen mit ungueltiger Menge' AS check_name,
     COUNT(*) AS result
FROM asset_requests
WHERE quantity IS NULL OR quantity < 1;

-- 7) Lookup-Tabellen vorhanden und befuellt?
SELECT 'Tabelle lookup_ram vorhanden' AS check_name,
       IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'lookup_ram'),   'OK', 'FEHLT') AS result;
SELECT 'Tabelle lookup_ssd vorhanden' AS check_name,
       IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'lookup_ssd'),   'OK', 'FEHLT') AS result;
SELECT 'Tabelle lookup_cores vorhanden' AS check_name,
       IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'lookup_cores'), 'OK', 'FEHLT') AS result;
SELECT 'Tabelle lookup_os vorhanden' AS check_name,
       IF(EXISTS(SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'lookup_os'),    'OK', 'FEHLT') AS result;

SELECT 'Eintraege lookup_ram' AS check_name, COUNT(*) AS result FROM lookup_ram;
SELECT 'Eintraege lookup_ssd' AS check_name, COUNT(*) AS result FROM lookup_ssd;
SELECT 'Eintraege lookup_cores' AS check_name, COUNT(*) AS result FROM lookup_cores;
SELECT 'Eintraege lookup_os' AS check_name, COUNT(*) AS result FROM lookup_os;

-- 8) Mindestdaten vorhanden?
SELECT 'Anzahl Status-Labels' AS check_name, COUNT(*) AS result FROM status_labels;
SELECT 'Anzahl Standorte' AS check_name, COUNT(*) AS result FROM locations;
SELECT 'Anzahl Kategorien' AS check_name, COUNT(*) AS result FROM categories;
SELECT 'Anzahl Hersteller' AS check_name, COUNT(*) AS result FROM manufacturers;
SELECT 'Anzahl Asset-Modelle' AS check_name, COUNT(*) AS result FROM asset_models;
SELECT 'Anzahl Assets gesamt' AS check_name, COUNT(*) AS result FROM assets;

SELECT 'Mindestens 1 Admin vorhanden' AS check_name,
       IF(EXISTS(SELECT 1 FROM users WHERE role = 'admin' AND can_login = 1), 'OK', 'KEIN ADMIN') AS result;

SELECT 'Settings-Zeile vorhanden' AS check_name,
       IF(EXISTS(SELECT 1 FROM settings WHERE id = 1), 'OK', 'FEHLT') AS result;

-- 9) Login-Log-Tabelle vorhanden und befuellt?
SELECT 'Tabelle login_logs vorhanden' AS check_name,
       IF(EXISTS(SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = @schema_name AND TABLE_NAME = 'login_logs'),
          'OK', 'FEHLT') AS result;

SELECT 'Spalte login_logs.reason vorhanden' AS check_name,
  IF(EXISTS(
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'login_logs'
        AND COLUMN_NAME = 'reason'
  ), 'OK', 'FEHLT') AS result;

SELECT 'login_logs.action um Fehler-/Block-Events erweitert' AS check_name,
  IF(EXISTS(
      SELECT 1
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @schema_name
        AND TABLE_NAME = 'login_logs'
        AND COLUMN_NAME = 'action'
        AND COLUMN_TYPE LIKE '%login_failed%'
        AND COLUMN_TYPE LIKE '%login_blocked%'
  ), 'OK', 'FEHLT/FALSCHER ENUM') AS result;

SELECT 'Eintraege login_logs gesamt' AS check_name, COUNT(*) AS result FROM login_logs;

SELECT 'Login-Logs letzte 24h'  AS check_name,
       COUNT(*) AS result
  FROM login_logs
 WHERE created_at >= NOW() - INTERVAL 1 DAY;

SELECT 'Letzte Logins (max. 10)' AS check_name,
       username, action, ip_address, created_at
  FROM login_logs
 ORDER BY created_at DESC
 LIMIT 10;

-- 10) Optional: Detailausgabe bei Problemen (auskommentiert)
-- SELECT * FROM categories WHERE kuerzel IS NULL OR kuerzel = '' OR kuerzel NOT REGEXP '^[A-Z]{2}$';
-- SELECT u.* FROM users u LEFT JOIN locations l ON l.id = u.location_id WHERE u.location_id IS NOT NULL AND l.id IS NULL;
