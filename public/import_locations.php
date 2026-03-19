<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireAdmin();

$db = Database::getInstance();
$masterData = new MasterDataController($db);

$error = null;
$success = null;
$report = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $delimiter = $_POST['delimiter'] ?? ';';
    if ($delimiter === 'tab') $delimiter = "\t";
    $file = $_FILES['csv_file']['tmp_name'];

    if (!is_uploaded_file($file)) {
        $error = "Dateiupload fehlgeschlagen.";
    } else {
        // Vorhandene Standorte für Duplikat-Prüfung laden
        $existingLocations = $masterData->getLocations();
        $locationNames = [];
        foreach ($existingLocations as $loc) {
            $locationNames[strtolower(trim($loc['name']))] = true;
        }

        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, $delimiter, '"', "");
            
            if (!$header || count($header) < 1) {
                $error = "Ungültiges CSV-Format. Mindestens 1 Spalte erforderlich (name).";
            } else {
                $rowCount = 0;
                $inserted = 0;
                $skipped = 0;
                $failed = 0;

                while (($row = fgetcsv($handle, 1000, $delimiter, '"', "")) !== FALSE) {
                    $rowCount++;
                    if (count($row) < 1 || empty(trim($row[0]))) {
                        $failed++;
                        $report[] = "Zeile $rowCount: Standort-Name fehlt.";
                        continue;
                    }

                    $name = trim($row[0]);
                    $address = isset($row[1]) ? trim($row[1]) : null;
                    $city = isset($row[2]) ? trim($row[2]) : null;
                    $kuerzel = isset($row[3]) ? trim($row[3]) : null;

                    // Duplikat-Prüfung
                    if (isset($locationNames[strtolower($name)])) {
                        $skipped++;
                        $report[] = "Zeile $rowCount: Standort '$name' existiert bereits – übersprungen.";
                        continue;
                    }

                    $locationData = [
                        'name' => $name,
                        'address' => !empty($address) ? $address : null,
                        'city' => !empty($city) ? $city : null,
                        'kuerzel' => !empty($kuerzel) ? $kuerzel : null
                    ];

                    try {
                        if ($masterData->addLocation($locationData)) {
                            $inserted++;
                            $locationNames[strtolower($name)] = true;
                        } else {
                            $failed++;
                            $report[] = "Zeile $rowCount: Fehler beim Erstellen von '$name'.";
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        $report[] = "Zeile $rowCount: " . $e->getMessage();
                    }
                }
                fclose($handle);
                $success = "Import abgeschlossen. Erstellt: $inserted, Übersprungen (Duplikat): $skipped, Fehlgeschlagen: $failed.";
            }
        } else {
            $error = "Datei konnte nicht geöffnet werden.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standorte importieren - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <div class="sidebar">
        <div class="logo">Mini-Snipe</div>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="assets.php" class="nav-link"><i class="fas fa-laptop"></i> Assets</a>
            <a href="users.php" class="nav-link"><i class="fas fa-users"></i> User</a>
            <a href="locations.php" class="nav-link"><i class="fas fa-map-marker-alt"></i> Standorte</a>
            <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Verwaltung</a>
                <a href="settings_general.php" class="nav-link"><i class="fas fa-sliders-h"></i> Einstellungen</a>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <h1><i class="fas fa-map-marker-alt"></i> Standorte importieren</h1>
            <p style="color: var(--text-muted); margin-top: 0.5rem;">Lade deine Standorte aus einer CSV-Datei hoch.</p>
        </header>

        <div class="card" style="max-width: 600px;">
            <?php if ($error): ?>
                <div style="background: rgba(244, 63, 94, 0.1); border: 1px solid rgba(244, 63, 94, 0.2); color: var(--accent-rose); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
                    <strong>Fehler:</strong> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--accent-emerald); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
                    <strong>Erfolg:</strong> <?php echo htmlspecialchars($success); ?>
                </div>
                <?php if (!empty($report)): ?>
                    <div style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; max-height: 300px; overflow-y: auto;">
                        <strong style="color: var(--text-muted);">Importbericht:</strong>
                        <ul style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.5rem;">
                            <?php foreach ($report as $line): ?>
                                <li><?php echo htmlspecialchars($line); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                <a href="settings.php" class="btn btn-primary">Zurück zu Einstellungen</a>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data">
                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; color: var(--text-muted); margin-bottom: 0.5rem; font-size: 0.875rem;">CSV-Datei hochladen</label>
                        <input type="file" name="csv_file" accept=".csv" required style="display: block; width: 100%; padding: 0.5rem; border: 1px solid var(--glass-border); border-radius: 0.5rem; background: rgba(0,0,0,0.2); color: white; cursor: pointer; margin-bottom: 1rem;">
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="display: block; color: var(--text-muted); margin-bottom: 0.5rem; font-size: 0.875rem;">Trennzeichen</label>
                        <select name="delimiter" style="width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white;">
                            <option value=";">Semikolon (;) - Standardeinstellung</option>
                            <option value=",">Komma (,)</option>
                            <option value="tab">Tabulator</option>
                        </select>
                    </div>

                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">
                        <strong>Erforderliche Spalten:</strong> name<br>
                        <strong>Optionale Spalten:</strong> address, city, kuerzel
                    </p>

                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-upload"></i> Importieren</button>
                        <a href="settings.php" class="btn" style="flex:1; background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border); text-decoration: none; text-align: center;">Abbrechen</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
