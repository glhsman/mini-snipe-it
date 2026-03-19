<?php
$dir = __DIR__ . '/public/';
$files = glob($dir . '*.php');

foreach ($files as $file) {
    if (basename($file) === 'index.php') continue; // Bereits manuell erledigt
    
    $content = file_get_contents($file);
    
    // Prüfen, ob sidebar vorhanden
    if (strpos($content, '<div class="sidebar">') !== false) {
        // Prüfen, ob bereits inkludiert
        if (strpos($content, 'top_navbar.php') === false) {
            $insert = "<?php include_once __DIR__ . '/includes/top_navbar.php'; ?>\n    <div class=\"sidebar\">";
            $newContent = str_replace('<div class="sidebar">', $insert, $content);
            file_put_contents($file, $newContent);
            echo "Injected in " . basename($file) . "\n";
        } else {
            echo "Already exists in " . basename($file) . "\n";
        }
    }
}
