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

-- 2) Kritische Spalten vorhanden?
SELECT 'Spalte categories.kuerzel vorhanden' AS check_name,
       IF(EXISTS(
           SELECT 1 FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = @schema_name
             AND TABLE_NAME = 'categories'
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

-- 6) Optional: Detailausgabe bei Problemen (auskommentiert)
-- SELECT * FROM categories WHERE kuerzel IS NULL OR kuerzel = '' OR kuerzel NOT REGEXP '^[A-Z]{2}$';
-- SELECT u.* FROM users u LEFT JOIN locations l ON l.id = u.location_id WHERE u.location_id IS NOT NULL AND l.id IS NULL;
