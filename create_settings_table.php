<?php
require_once __DIR__ . '/config/db.php';

try {
    $db = Database::getInstance();
    
    $sql = "CREATE TABLE IF NOT EXISTS settings (
        id INT PRIMARY KEY,
        site_name VARCHAR(255) DEFAULT 'Mini-Snipe',
        branding_type VARCHAR(20) DEFAULT 'text',
        site_logo VARCHAR(255) DEFAULT NULL
    )";
    
    $db->exec($sql);
    echo "Tabelle 'settings' erstellt oder existiert bereits.\n";
    
    // Initialer Datensatz
    $stmt = $db->query("SELECT COUNT(*) FROM settings WHERE id = 1");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO settings (id, site_name, branding_type) VALUES (1, 'Mini-Snipe', 'text')");
        echo "Initialer Datensatz eingefügt.\n";
    } else {
        echo "Initialer Datensatz existiert bereits.\n";
    }
} catch (Exception $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
