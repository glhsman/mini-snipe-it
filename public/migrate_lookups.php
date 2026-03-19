<?php
// migrate_lookups.php

function runLookupMigration($db) {
    try {
        // 1. Tabellen erstellen
        $db->exec("CREATE TABLE IF NOT EXISTS lookup_ram (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(50) UNIQUE)");
        $db->exec("CREATE TABLE IF NOT EXISTS lookup_ssd (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(50) UNIQUE)");
        $db->exec("CREATE TABLE IF NOT EXISTS lookup_cores (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(50) UNIQUE)");
        $db->exec("CREATE TABLE IF NOT EXISTS lookup_os (id INT AUTO_INCREMENT PRIMARY KEY, value VARCHAR(100) UNIQUE)");

        // 2. Standardwerte einfügen (falls leer)
        $defaultRam = ['4 GB', '8 GB', '16 GB', '32 GB', '64 GB'];
        foreach ($defaultRam as $v) $db->exec("INSERT IGNORE INTO lookup_ram (value) VALUES ('$v')");

        $defaultSsd = ['128 GB', '256 GB', '512 GB', '1 TB', '2 TB'];
        foreach ($defaultSsd as $v) $db->exec("INSERT IGNORE INTO lookup_ssd (value) VALUES ('$v')");

        $defaultCores = ['2', '4', '6', '8', '10', '12', '16'];
        foreach ($defaultCores as $v) $db->exec("INSERT IGNORE INTO lookup_cores (value) VALUES ('$v')");

        $defaultOs = ['Windows 10', 'Windows 11', 'macOS', 'Linux', 'Android', 'iOS'];
        foreach ($defaultOs as $v) $db->exec("INSERT IGNORE INTO lookup_os (value) VALUES ('$v')");

        // 3. Daten aus Assets extrahieren (Migration)
        // RAM
        $db->exec("INSERT IGNORE INTO lookup_ram (value) SELECT DISTINCT CONCAT(ram, ' GB') FROM assets WHERE ram IS NOT NULL AND ram > 0");
        // SSD
        $db->exec("INSERT IGNORE INTO lookup_ssd (value) SELECT DISTINCT CONCAT(ssd_size, ' GB') FROM assets WHERE ssd_size IS NOT NULL AND ssd_size > 0");
        // Cores
        $db->exec("INSERT IGNORE INTO lookup_cores (value) SELECT DISTINCT cores FROM assets WHERE cores IS NOT NULL AND cores > 0");
        // OS
        $db->exec("INSERT IGNORE INTO lookup_os (value) SELECT DISTINCT os_version FROM assets WHERE os_version IS NOT NULL AND os_version != ''");

        // 4. Daten in Assets updaten (IDs statt Rohwerte)
        // RAM
        $db->exec("UPDATE assets a JOIN lookup_ram l ON CONCAT(a.ram, ' GB') = l.value SET a.ram = l.id WHERE a.ram IS NOT NULL AND a.ram > 0 AND a.ram NOT IN (SELECT id FROM lookup_ram)");
        
        // SSD
        $db->exec("UPDATE assets a JOIN lookup_ssd l ON CONCAT(a.ssd_size, ' GB') = l.value SET a.ssd_size = l.id WHERE a.ssd_size IS NOT NULL AND a.ssd_size > 0 AND a.ssd_size NOT IN (SELECT id FROM lookup_ssd)");
        
        // Cores
        $db->exec("UPDATE assets a JOIN lookup_cores l ON a.cores = l.value SET a.cores = l.id WHERE a.cores IS NOT NULL AND a.cores > 0 AND a.cores NOT IN (SELECT id FROM lookup_cores)");
        
        // OS
        $db->exec("UPDATE assets a JOIN lookup_os l ON a.os_version = l.value SET a.os_version = l.id WHERE a.os_version IS NOT NULL AND a.os_version != '' AND a.os_version NOT IN (SELECT id FROM lookup_os)");

        return true;
    } catch (Exception $e) {
        // Fehler protokollieren oder zurückgeben
        return false;
    }
}

