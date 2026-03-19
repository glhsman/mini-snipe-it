<?php
$file = __DIR__ . '/public/settings.php';
$content = file_get_contents($file);

$search = '$db = Database::getInstance();';
$insert = "\n// Auto-Migration für Hardware-Lookups\n"
        . "\$stmt = \$db->query(\"SHOW TABLES LIKE 'lookup_ram'\");\n"
        . "if (\$stmt->rowCount() == 0) {\n"
        . "    require_once __DIR__ . '/migrate_lookups.php';\n"
        . "    runLookupMigration(\$db);\n"
        . "}\n";

if (strpos($content, $search) !== false && strpos($content, 'runLookupMigration') === false) {
    $newContent = str_replace($search, $search . $insert, $content);
    file_put_contents($file, $newContent);
    echo "Migration injected successfully.\n";
} else {
    echo "Either search not found or already injected.\n";
}
