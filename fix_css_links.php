<?php
$dir = __DIR__ . '/public/';
$files = glob($dir . '*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Muster für den CSS Link (variabel mit Leerzeichen/Tabs)
    $pattern = '/<link\s+rel="stylesheet"\s+href="assets\/css\/style\.css"\s*>/i';
    
    if (preg_match($pattern, $content)) {
        // Prüfen, ob bereits ein Cache-Breaker (?v=) existiert
        if (strpos($content, 'style.css?v=') === false) {
            $replace = '<link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . \'/assets/css/style.css\'); ?>">';
            $newContent = preg_replace($pattern, $replace, $content);
            file_put_contents($file, $newContent);
            echo "Fixed CSS in " . basename($file) . "\n";
        } else {
            echo "Cache-Breaker already exists in " . basename($file) . "\n";
        }
    }
}
