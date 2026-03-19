-- Tabellen für Mini-Snipe IT Asset Management
-- (Die Datenbank muss bereits existieren und über den Wizard ausgewählt worden sein)

-- 1. Standorte (Locations)
CREATE TABLE IF NOT EXISTS locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(255),
    city VARCHAR(100),
    kuerzel VARCHAR(2), -- NEU: 2-Buchstaben-Kürzel
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Hersteller (Manufacturers)
CREATE TABLE IF NOT EXISTS manufacturers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    url VARCHAR(255),
    support_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Kategorien (Categories)
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    kuerzel VARCHAR(2) UNIQUE, -- 2-Buchstaben-Kuerzel fuer Asset-Tag-Generierung
    category_type ENUM('asset', 'accessory', 'consumable', 'component') DEFAULT 'asset',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Status (Status Labels)
CREATE TABLE IF NOT EXISTS status_labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    status_type ENUM('deployable', 'pending', 'archived', 'undeployable') DEFAULT 'deployable',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Asset Modelle (Asset Models)
CREATE TABLE IF NOT EXISTS asset_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    manufacturer_id INT,
    category_id INT,
    model_number VARCHAR(255),
    has_sim_fields BOOLEAN DEFAULT 0,
    has_hardware_fields BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- 6. Benutzer (Users)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    email VARCHAR(255),
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255),
    can_login TINYINT(1) NOT NULL DEFAULT 1,
    role ENUM('admin', 'editor', 'user') DEFAULT 'user',
    location_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
);

-- 6b. Lookup-Tabellen für Hardware (NEU)
CREATE TABLE IF NOT EXISTS lookup_ram (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(50) UNIQUE);
CREATE TABLE IF NOT EXISTS lookup_ssd (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(50) UNIQUE);
CREATE TABLE IF NOT EXISTS lookup_cores (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(50) UNIQUE);
CREATE TABLE IF NOT EXISTS lookup_os (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(100) UNIQUE);

-- 6c. Allgemeine Einstellungen
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

-- 7. Assets (Hardware)
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    asset_tag VARCHAR(100) UNIQUE,
    serial VARCHAR(100) UNIQUE,
    model_id INT,
    status_id INT,
    location_id INT,
    user_id INT, -- Derzeitiger Besitzer
    purchase_date DATE,
    notes TEXT,
    pin VARCHAR(4) NULL,
    puk VARCHAR(8) NULL,
    rufnummer VARCHAR(20) NULL,
    mac_adresse VARCHAR(17) NULL,
    ram INT NULL,
    ssd_size INT NULL,
    cores INT NULL,
    os_version INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (model_id) REFERENCES asset_models(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES status_labels(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

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

-- ---------------------------------------------------------------------------
-- Demo-Stammdaten (4 Eintraege je Tabelle fuer Neuinstallationen)
-- Alle INSERT IGNORE: sicher bei mehrfachem Ausfuehren wo UNIQUE-Keys greifen
-- ---------------------------------------------------------------------------

INSERT INTO locations (name, address, city, kuerzel) VALUES
    ('Hauptstandort',    'Musterstraße 1',   'Berlin',   'HS'),
    ('Zweigstelle Süd', 'Bahnhofstr. 10',   'München',  'ZS'),
    ('Zweigstelle Nord', 'Hafenweg 5',       'Hamburg',  'ZN'),
    ('Homeoffice Pool',  NULL,               NULL,       'HO');

INSERT INTO status_labels (name, status_type) VALUES
    ('Einsatzbereit', 'deployable'),
    ('Ausgegeben',    'deployable'),
    ('In Reparatur',  'pending'),
    ('Defekt',        'undeployable'),
    ('Ausgemustert',  'archived');

INSERT INTO categories (name, kuerzel) VALUES
    ('Laptops',      'LP'),
    ('Smartphones',  'SP'),
    ('Monitore',     'MN'),
    ('Tablets',      'TB');

INSERT INTO manufacturers (name) VALUES
    ('Apple'),
    ('Dell'),
    ('Lenovo'),
    ('Samsung');

-- Modelle: manufacturer_id / category_id entsprechen der Reihenfolge der INSERTs oben
INSERT INTO asset_models (name, manufacturer_id, category_id, has_hardware_fields) VALUES
    ('MacBook Pro 14"',    1, 1, 1),   -- Apple   / Laptops
    ('Latitude 5420',      2, 1, 1),   -- Dell    / Laptops
    ('ThinkPad X1 Carbon', 3, 1, 1),   -- Lenovo  / Laptops
    ('Galaxy S24',         4, 2, 0);   -- Samsung / Smartphones

-- Benutzer (alle Demo-Passwoerter: password)
-- admin    = System-Administrator
-- mmuster  = Lager-Editor
-- aschmidt = normaler Benutzer (Berlin)
-- tmueller = normaler Benutzer (Hamburg)
INSERT IGNORE INTO users
    (first_name, last_name, email,                         username,   password,                                                      can_login, role,   location_id) VALUES
    ('System',  'Admin',   'admin@example.com',            'admin',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'admin',  1),
    ('Max',     'Muster',  'max.muster@example.com',       'mmuster',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'editor', 1),
    ('Anna',    'Schmidt', 'anna.schmidt@example.com',     'aschmidt', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'user',   1),
    ('Thomas',  'Müller',  'thomas.mueller@example.com',   'tmueller', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'user',   3);

INSERT INTO settings (id, site_name, branding_type, company_address, protocol_header_text, protocol_footer_text)
VALUES (
    1,
    'Mini-Snipe',
    'text',
    'Musterstraße 1, 10115 Berlin',
    'Die unten aufgeführte IT-Hardware wird hiermit bestätigt. Mit Ihrer Unterschrift bestätigen Sie den ordnungsgemäßen Erhalt bzw. die vollständige Rückgabe der aufgelisteten Geräte.',
    'IT-Protokoll – Für Ihre/unsere Unterlagen.'
)
ON DUPLICATE KEY UPDATE id = id;

-- Login-Protokoll
CREATE TABLE IF NOT EXISTS login_logs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT  NULL,
    username    VARCHAR(100) NOT NULL,
    action      ENUM('login','logout','login_failed','login_blocked') NOT NULL,
    reason      VARCHAR(100) NULL,
    ip_address  VARCHAR(45) NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_logs_created (created_at),
    INDEX idx_login_logs_user    (user_id)
);
ON DUPLICATE KEY UPDATE
    company_address      = COALESCE(NULLIF(settings.company_address, ''),      VALUES(company_address)),
    protocol_header_text = COALESCE(NULLIF(settings.protocol_header_text, ''), VALUES(protocol_header_text)),
    protocol_footer_text = COALESCE(NULLIF(settings.protocol_footer_text, ''), VALUES(protocol_footer_text));

-- Demo-Assets
-- IDs beziehen sich auf die oben eingefuegten Stammdaten (Frisch-Installation)
-- status_id: 1=Einsatzbereit  2=Ausgegeben  3=In Reparatur
-- user_id 3 = aschmidt (haelt das ausgegebene Geraet)
INSERT IGNORE INTO assets (name, asset_tag, serial, model_id, status_id, location_id, user_id) VALUES
    ('MacBook Pro 14 – #001', 'HSLP0001', 'SN-MBP-2024-001', 1, 1, 1, NULL),
    ('Dell Latitude – #001',  'HSLP0002', 'SN-DL-2024-001',  2, 2, 1, 3),
    ('ThinkPad X1 – #001',    'ZNLP0001', 'SN-TP-2024-001',  3, 3, 3, NULL),
    ('Galaxy S24 – #001',     'ZSSP0001', 'SN-GS-2024-001',  4, 1, 2, NULL);

-- Lookup-Standardwerte
INSERT IGNORE INTO lookup_ram    (value) VALUES ('4 GB'),   ('8 GB'),   ('16 GB'),  ('32 GB'),  ('64 GB');
INSERT IGNORE INTO lookup_ssd    (value) VALUES ('128 GB'), ('256 GB'), ('512 GB'), ('1 TB'),   ('2 TB');
INSERT IGNORE INTO lookup_cores  (value) VALUES ('2'),      ('4'),      ('6'),      ('8'),      ('10'),  ('12'),  ('16');
INSERT IGNORE INTO lookup_os     (value) VALUES ('Windows 10'), ('Windows 11'), ('macOS'), ('Linux'), ('Android'), ('iOS');
