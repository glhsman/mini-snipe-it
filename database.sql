-- Tabellen fuer Mini-Snipe IT Asset Management
-- (Die Datenbank muss bereits existieren und ueber den Wizard ausgewaehlt worden sein)

-- 1. Standorte (Locations)
CREATE TABLE IF NOT EXISTS locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address VARCHAR(255),
    city VARCHAR(100),
    kuerzel VARCHAR(2),
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
    kuerzel VARCHAR(2) UNIQUE,
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
    serial_number_required BOOLEAN DEFAULT 1,
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
    personalnummer VARCHAR(10),
    vorgesetzter VARCHAR(100),
    is_activ TINYINT(1) NOT NULL DEFAULT 1,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255),
    can_login TINYINT(1) NOT NULL DEFAULT 1,
    role ENUM('admin', 'editor', 'user') DEFAULT 'user',
    location_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
);

-- 6b. Lookup-Tabellen fuer Hardware
CREATE TABLE IF NOT EXISTS lookup_ram (
    id INT AUTO_INCREMENT PRIMARY KEY,
    value VARCHAR(50) UNIQUE
);
CREATE TABLE IF NOT EXISTS lookup_ssd (
    id INT AUTO_INCREMENT PRIMARY KEY,
    value VARCHAR(50) UNIQUE
);
CREATE TABLE IF NOT EXISTS lookup_cores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    value VARCHAR(50) UNIQUE
);
CREATE TABLE IF NOT EXISTS lookup_os (
    id INT AUTO_INCREMENT PRIMARY KEY,
    value VARCHAR(100) UNIQUE
);

-- 6c. Allgemeine Einstellungen
CREATE TABLE IF NOT EXISTS settings (
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
);

-- 7. Assets (Hardware)
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    asset_tag VARCHAR(100) UNIQUE,
    serial VARCHAR(100) UNIQUE,
    serial_number_required TINYINT(1) NOT NULL DEFAULT 1,
    model_id INT,
    status_id INT,
    location_id INT,
    user_id INT,
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
    archiv_bit TINYINT(1) NOT NULL DEFAULT 0,
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

CREATE TABLE IF NOT EXISTS login_logs (
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
);

-- ---------------------------------------------------------------------------
-- Demo-Stammdaten fuer Neuinstallationen
-- Wiederholbares Seed-Schema, damit ein erneuter Lauf nach Teilerfolg nicht
-- an doppelten Vorbelegungen scheitert.
-- ---------------------------------------------------------------------------

INSERT INTO locations (name, address, city, kuerzel)
SELECT 'Hauptstandort', 'Musterstrasse 1', 'Berlin', 'HS'
WHERE NOT EXISTS (SELECT 1 FROM locations WHERE kuerzel = 'HS');
INSERT INTO locations (name, address, city, kuerzel)
SELECT 'Zweigstelle Sued', 'Bahnhofstr. 10', 'Muenchen', 'ZS'
WHERE NOT EXISTS (SELECT 1 FROM locations WHERE kuerzel = 'ZS');
INSERT INTO locations (name, address, city, kuerzel)
SELECT 'Zweigstelle Nord', 'Hafenweg 5', 'Hamburg', 'ZN'
WHERE NOT EXISTS (SELECT 1 FROM locations WHERE kuerzel = 'ZN');
INSERT INTO locations (name, address, city, kuerzel)
SELECT 'Homeoffice Pool', NULL, NULL, 'HO'
WHERE NOT EXISTS (SELECT 1 FROM locations WHERE kuerzel = 'HO');

INSERT INTO status_labels (name, status_type)
SELECT 'Einsatzbereit', 'deployable'
WHERE NOT EXISTS (SELECT 1 FROM status_labels WHERE name = 'Einsatzbereit');
INSERT INTO status_labels (name, status_type)
SELECT 'Ausgegeben', 'deployable'
WHERE NOT EXISTS (SELECT 1 FROM status_labels WHERE name = 'Ausgegeben');
INSERT INTO status_labels (name, status_type)
SELECT 'In Reparatur', 'pending'
WHERE NOT EXISTS (SELECT 1 FROM status_labels WHERE name = 'In Reparatur');
INSERT INTO status_labels (name, status_type)
SELECT 'Defekt', 'undeployable'
WHERE NOT EXISTS (SELECT 1 FROM status_labels WHERE name = 'Defekt');
INSERT INTO status_labels (name, status_type)
SELECT 'Ausgemustert', 'archived'
WHERE NOT EXISTS (SELECT 1 FROM status_labels WHERE name = 'Ausgemustert');

INSERT INTO categories (name, kuerzel)
SELECT 'Laptops', 'LP'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE kuerzel = 'LP');
INSERT INTO categories (name, kuerzel)
SELECT 'Smartphones', 'SP'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE kuerzel = 'SP');
INSERT INTO categories (name, kuerzel)
SELECT 'Monitore', 'MN'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE kuerzel = 'MN');
INSERT INTO categories (name, kuerzel)
SELECT 'Tablets', 'TB'
WHERE NOT EXISTS (SELECT 1 FROM categories WHERE kuerzel = 'TB');

INSERT INTO manufacturers (name)
SELECT 'Apple'
WHERE NOT EXISTS (SELECT 1 FROM manufacturers WHERE name = 'Apple');
INSERT INTO manufacturers (name)
SELECT 'Dell'
WHERE NOT EXISTS (SELECT 1 FROM manufacturers WHERE name = 'Dell');
INSERT INTO manufacturers (name)
SELECT 'Lenovo'
WHERE NOT EXISTS (SELECT 1 FROM manufacturers WHERE name = 'Lenovo');
INSERT INTO manufacturers (name)
SELECT 'Samsung'
WHERE NOT EXISTS (SELECT 1 FROM manufacturers WHERE name = 'Samsung');

INSERT INTO asset_models (name, manufacturer_id, category_id, has_hardware_fields)
SELECT 'MacBook Pro 14"', ma.id, c.id, 1
FROM manufacturers ma
INNER JOIN categories c ON c.kuerzel = 'LP'
WHERE ma.name = 'Apple'
  AND NOT EXISTS (SELECT 1 FROM asset_models WHERE name = 'MacBook Pro 14"');
INSERT INTO asset_models (name, manufacturer_id, category_id, has_hardware_fields)
SELECT 'Latitude 5420', ma.id, c.id, 1
FROM manufacturers ma
INNER JOIN categories c ON c.kuerzel = 'LP'
WHERE ma.name = 'Dell'
  AND NOT EXISTS (SELECT 1 FROM asset_models WHERE name = 'Latitude 5420');
INSERT INTO asset_models (name, manufacturer_id, category_id, has_hardware_fields)
SELECT 'ThinkPad X1 Carbon', ma.id, c.id, 1
FROM manufacturers ma
INNER JOIN categories c ON c.kuerzel = 'LP'
WHERE ma.name = 'Lenovo'
  AND NOT EXISTS (SELECT 1 FROM asset_models WHERE name = 'ThinkPad X1 Carbon');
INSERT INTO asset_models (name, manufacturer_id, category_id, has_hardware_fields)
SELECT 'Galaxy S24', ma.id, c.id, 0
FROM manufacturers ma
INNER JOIN categories c ON c.kuerzel = 'SP'
WHERE ma.name = 'Samsung'
  AND NOT EXISTS (SELECT 1 FROM asset_models WHERE name = 'Galaxy S24');

-- Benutzer (alle Demo-Passwoerter: password)
INSERT IGNORE INTO users
    (first_name, last_name, email, username, password, can_login, role, location_id)
VALUES
    ('System', 'Admin', 'admin@example.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'admin', 1),
    ('Max', 'Muster', 'max.muster@example.com', 'mmuster', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'editor', 1),
    ('Anna', 'Schmidt', 'anna.schmidt@example.com', 'aschmidt', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'user', 1),
    ('Thomas', 'Mueller', 'thomas.mueller@example.com', 'tmueller', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 'user', 3);

INSERT INTO settings (id, site_name, branding_type, company_address, protocol_header_text, protocol_footer_text)
VALUES (
    1,
    'Mini-Snipe',
    'text',
    'Musterstrasse 1, 10115 Berlin',
    'Die unten aufgefuehrte IT-Hardware wird hiermit bestaetigt. Mit Ihrer Unterschrift bestaetigen Sie den ordnungsgemaessen Erhalt bzw. die vollstaendige Rueckgabe der aufgelisteten Geraete.',
    'IT-Protokoll - Fuer Ihre und unsere Unterlagen.'
)
ON DUPLICATE KEY UPDATE
    company_address = COALESCE(NULLIF(settings.company_address, ''), VALUES(company_address)),
    protocol_header_text = COALESCE(NULLIF(settings.protocol_header_text, ''), VALUES(protocol_header_text)),
    protocol_footer_text = COALESCE(NULLIF(settings.protocol_footer_text, ''), VALUES(protocol_footer_text));

-- Demo-Assets
INSERT INTO assets (name, asset_tag, serial, model_id, status_id, location_id, user_id)
SELECT 'MacBook Pro 14 - #001', 'HSLP0001', 'SN-MBP-2024-001', am.id, sl.id, l.id, NULL
FROM asset_models am
INNER JOIN status_labels sl ON sl.name = 'Einsatzbereit'
INNER JOIN locations l ON l.kuerzel = 'HS'
WHERE am.name = 'MacBook Pro 14"'
  AND NOT EXISTS (SELECT 1 FROM assets WHERE asset_tag = 'HSLP0001');
INSERT INTO assets (name, asset_tag, serial, model_id, status_id, location_id, user_id)
SELECT 'Dell Latitude - #001', 'HSLP0002', 'SN-DL-2024-001', am.id, sl.id, l.id, u.id
FROM asset_models am
INNER JOIN status_labels sl ON sl.name = 'Ausgegeben'
INNER JOIN locations l ON l.kuerzel = 'HS'
INNER JOIN users u ON u.username = 'aschmidt'
WHERE am.name = 'Latitude 5420'
  AND NOT EXISTS (SELECT 1 FROM assets WHERE asset_tag = 'HSLP0002');
INSERT INTO assets (name, asset_tag, serial, model_id, status_id, location_id, user_id)
SELECT 'ThinkPad X1 - #001', 'ZNLP0001', 'SN-TP-2024-001', am.id, sl.id, l.id, NULL
FROM asset_models am
INNER JOIN status_labels sl ON sl.name = 'In Reparatur'
INNER JOIN locations l ON l.kuerzel = 'ZN'
WHERE am.name = 'ThinkPad X1 Carbon'
  AND NOT EXISTS (SELECT 1 FROM assets WHERE asset_tag = 'ZNLP0001');
INSERT INTO assets (name, asset_tag, serial, model_id, status_id, location_id, user_id)
SELECT 'Galaxy S24 - #001', 'ZSSP0001', 'SN-GS-2024-001', am.id, sl.id, l.id, NULL
FROM asset_models am
INNER JOIN status_labels sl ON sl.name = 'Einsatzbereit'
INNER JOIN locations l ON l.kuerzel = 'ZS'
WHERE am.name = 'Galaxy S24'
  AND NOT EXISTS (SELECT 1 FROM assets WHERE asset_tag = 'ZSSP0001');

-- Lookup-Standardwerte
INSERT IGNORE INTO lookup_ram (value) VALUES ('4 GB'), ('8 GB'), ('16 GB'), ('32 GB'), ('64 GB');
INSERT IGNORE INTO lookup_ssd (value) VALUES ('128 GB'), ('256 GB'), ('512 GB'), ('1 TB'), ('2 TB');
INSERT IGNORE INTO lookup_cores (value) VALUES ('2'), ('4'), ('6'), ('8'), ('10'), ('12'), ('16');
INSERT IGNORE INTO lookup_os (value) VALUES ('Windows 10'), ('Windows 11'), ('macOS'), ('Linux'), ('Android'), ('iOS');
