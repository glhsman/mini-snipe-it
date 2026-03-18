<?php
require_once __DIR__ . '/../config/db.php';

$db = Database::getInstance();

try {
    // Index-Löschung für MySQL/MariaDB
    $db->exec("ALTER TABLE users DROP INDEX email");
    echo "SUCCESS: Index 'email' wurde von der Tabelle 'users' entfernt.";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
?>
