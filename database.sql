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
    model_number VARCHAR(100),
    kuerzel VARCHAR(2), -- NEU: 2-Buchstaben-Kürzel
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

-- 6. Benutzer (Users)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    email VARCHAR(255) UNIQUE,
    username VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'editor', 'user') DEFAULT 'user',
    location_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
);

-- 7. Assets (Hardware)
CREATE TABLE IF NOT EXISTS assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    asset_tag VARCHAR(100) UNIQUE NOT NULL,
    serial VARCHAR(100),
    model_id INT,
    status_id INT,
    location_id INT,
    user_id INT, -- Derzeitiger Besitzer
    purchase_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (model_id) REFERENCES asset_models(id) ON DELETE SET NULL,
    FOREIGN KEY (status_id) REFERENCES status_labels(id) ON DELETE SET NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Initiale Testdaten
INSERT INTO locations (name, city) VALUES ('Hauptquartier', 'Berlin'), ('Zweigstelle Süd', 'München');
INSERT INTO status_labels (name, status_type) VALUES ('Einsatzbereit', 'deployable'), ('In Reparatur', 'pending'), ('Ausgemustert', 'archived');
INSERT INTO categories (name) VALUES ('Laptops'), ('Smartphones'), ('Monitore');
INSERT INTO manufacturers (name) VALUES ('Apple'), ('Dell'), ('Lenovo');
INSERT INTO asset_models (name, manufacturer_id, category_id) VALUES ('MacBook Pro 14', 1, 1), ('Latitude 5420', 2, 1);

-- Standard-Admin (Passwort: password)
INSERT INTO users (first_name, last_name, email, username, password, role) 
VALUES ('System', 'Admin', 'admin@example.com', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
