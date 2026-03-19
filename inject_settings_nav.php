<?php
$file = __DIR__ . '/public/settings.php';
$content = file_get_contents($file);

$search = '<h1>Stammdatenverwaltung</h1>';
$insert = '
        <div style="display: flex; gap: 0.5rem; margin-top: 1rem; margin-bottom: 2rem;">
            <a href="#models" class="btn btn-sm" style="background: rgba(255,255,255,0.05);">Modelle</a>
            <a href="#categories" class="btn btn-sm" style="background: rgba(255,255,255,0.05);">Kategorien</a>
            <a href="#manufacturers" class="btn btn-sm" style="background: rgba(255,255,255,0.05);">Hersteller</a>
            <a href="#hardware-options" class="btn btn-sm" style="background: var(--primary-color); color: white;">Hardware-Optionen</a>
        </div>
';

if (strpos($content, $search) !== false && strpos($content, 'href="#hardware-options"') === false) {
    $content = str_replace($search, $search . $insert, $content);
    file_put_contents($file, $content);
    echo "Navigation injected.\n";
} else {
    echo "Already contains navigation or header not found.\n";
}
