<?php
try {
    $dsn = "mysql:host=localhost;dbname=asset_db;charset=utf8mb4";
    $db = new \PDO($dsn, 'root', '');
    $db->exec("ALTER TABLE users DROP INDEX email");
    echo "SUCCESS: Index 'email' wurde mittels root-Zugriff von 'users' entfernt.";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
