<?php
require_once __DIR__ . '/../config/db.php';
$db = Database::getInstance();

echo "Locations table schema:\n";
try {
    $stmt = $db->query("DESCRIBE locations");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error describing locations: " . $e->getMessage() . "\n";
}

echo "\nAsset_models table schema:\n";
try {
    $stmt = $db->query("DESCRIBE asset_models");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "Error describing asset_models: " . $e->getMessage() . "\n";
}
?>
