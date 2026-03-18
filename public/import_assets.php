<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Controllers\MasterDataController;
use App\Controllers\UserController;
use App\Helpers\Auth;

Auth::requireAdmin();

$db = Database::getInstance();
$assetController = new AssetController($db);
$masterData = new MasterDataController($db);
$userController = new UserController($db);

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
        // --- Lookups laden ---
        
        $mans = $masterData->getManufacturers();
        $manMap = []; foreach ($mans as $m) $manMap[strtolower(trim($m['name']))] = $m['id'];

        $cats = $masterData->getCategories();
        $catMap = []; foreach ($cats as $c) $catMap[strtolower(trim($c['name']))] = $c['id'];

        $models = $masterData->getAssetModels();
        $modelMap = []; foreach ($models as $m) $modelMap[strtolower(trim($m['name']))] = $m['id'];

        $statusLabels = $masterData->getStatusLabels();
        $statusMap = []; foreach ($statusLabels as $s) $statusMap[strtolower(trim($s['name']))] = $s['id'];

        $locations = $masterData->getLocations();
        $locationMap = []; foreach ($locations as $l) $locationMap[strtolower(trim($l['name']))] = $l['id'];

        $users = $userController->getAllUsers();
        $userMap = []; foreach ($users as $u) $userMap[strtolower(trim($u['username']))] = $u['id'];
        
        // Assets für Lookup (UPDATE statt INSERT bei Duplikaten)
        $assets = $assetController->getAllAssets();
        $assetTagMap = [];
        $assetSerialMap = [];
        foreach ($assets as $a) {
            if (!empty($a['asset_tag'])) $assetTagMap[strtolower(trim($a['asset_tag']))] = $a['id'];
            if (!empty($a['serial'])) $assetSerialMap[strtolower(trim($a['serial']))] = $a['id'];
        }

        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, $delimiter, '"', "");
            
            if (!$header || count($header) < 5) {
                $error = "Ungültiges CSV-Format. Erkannt: " . ($header ? count($header) : 0) . " Spalte(n). " .
                         "Inhalt: '" . htmlspecialchars($header[0] ?? '-') . "'. " .
                         "Tipp: Datei-Trennzeichen prüfen! (Erwartet: asset_tag, name, model, manufacturer, category)";
            } else {
                $rowCount = 0; $inserted = 0; $updated = 0; $failed = 0;

                while (($row = fgetcsv($handle, 1000, $delimiter, '"', "")) !== FALSE) {
                    $rowCount++;
                    if (count($row) < 5) {
                        $failed++; $report[] = "Zeile $rowCount: Unzureichende Spaltenanzahl."; continue;
                    }

                    $assetTag = trim($row[0]);
                    $assetName = trim($row[1]);
                    $serial = isset($row[2]) ? trim($row[2]) : null;
                    $modelName = trim($row[3]);
                    $manufacturerName = isset($row[4]) ? trim($row[4]) : '';
                    $categoryName = isset($row[5]) ? trim($row[5]) : '';
                    $statusName = isset($row[6]) ? trim($row[6]) : '';
                    $locationName = isset($row[7]) ? trim($row[7]) : '';
                    $assignedUsername  = isset($row[8]) ? trim($row[8]) : '';
                    $assignedFirstName = isset($row[9]) ? trim($row[9]) : '';
                    $assignedLastName  = isset($row[10]) ? trim($row[10]) : '';

                    if (empty($assetName)) {
                        $failed++; $report[] = "Zeile $rowCount: Asset Name fehlt."; continue;
                    }

                    // --- Dynamic Creation or Lookup ---

                    // 1. Hersteller
                    $manufacturerId = null;
                    if (!empty($manufacturerName)) {
                        $mKey = strtolower($manufacturerName);
                        if (!isset($manMap[$mKey])) {
                            $masterData->createManufacturer($manufacturerName);
                            $manufacturerId = $db->lastInsertId();
                            $manMap[$mKey] = $manufacturerId;
                        } else {
                            $manufacturerId = $manMap[$mKey];
                        }
                    }

                    // 2. Kategorie
                    $categoryId = null;
                    if (!empty($categoryName)) {
                        $cKey = strtolower($categoryName);
                        if (!isset($catMap[$cKey])) {
                            try {
                                // Eindeutiges Kürzel generieren
                                $kuerzel = strtoupper(substr($categoryName, 0, 2));
                                $suffix = 1;
                                $tryKuerzel = $kuerzel;
                                while (true) {
                                    try {
                                        $masterData->createCategory(['name' => $categoryName, 'kuerzel' => $tryKuerzel]);
                                        $categoryId = $db->lastInsertId();
                                        $catMap[$cKey] = $categoryId;
                                        break;
                                    } catch (\Exception $inner) {
                                        // Kürzel belegt – per Name prüfen ob Kategorie schon existiert
                                        $stmt = $db->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");
                                        $stmt->execute([$categoryName]);
                                        $existingCat = $stmt->fetch();
                                        if ($existingCat) {
                                            $categoryId = $existingCat['id'];
                                            $catMap[$cKey] = $categoryId;
                                            break;
                                        }
                                        // Kürzel mit Nummer versuchen (z.B. S1, S2)
                                        $tryKuerzel = strtoupper(substr($categoryName, 0, 1)) . $suffix;
                                        $suffix++;
                                        if ($suffix > 9) {
                                            $failed++; $report[] = "Zeile $rowCount: Kategorie '$categoryName' konnte nicht erstellt werden (kein freies Kürzel)."; 
                                            continue 2;
                                        }
                                    }
                                }
                            } catch (\Exception $e) {
                                $failed++; $report[] = "Zeile $rowCount: Kategorie '$categoryName' – unerwarteter Fehler.";
                                continue;
                            }
                        } else {
                            $categoryId = $catMap[$cKey];
                        }
                    }

                    // 3. Modell
                    $modelId = null;
                    if (!empty($modelName)) {
                        $modKey = strtolower($modelName);
                        if (!isset($modelMap[$modKey])) {
                            $masterData->addAssetModel([
                                'name' => $modelName,
                                'manufacturer_id' => $manufacturerId,
                                'category_id' => $categoryId,
                                'model_number' => null
                            ]);
                            $modelId = $db->lastInsertId();
                            $modelMap[$modKey] = $modelId;
                        } else {
                            $modelId = $modelMap[$modKey];
                        }
                    }

                    // 4. Status
                    $statusId = 1; // Default
                    if (!empty($statusName)) {
                        $sKey = strtolower($statusName);
                        if (isset($statusMap[$sKey])) {
                            $statusId = $statusMap[$sKey];
                        }
                    }

                    // 5. Standort
                    $locationId = null;
                    if (!empty($locationName)) {
                        $lKey = strtolower($locationName);
                        if (isset($locationMap[$lKey])) {
                            $locationId = $locationMap[$lKey];
                        }
                    }

                    // 6. Benutzer
                    $userId = null;
                    if (!empty($assignedUsername)) {
                        $uKey = strtolower($assignedUsername);
                        if (isset($userMap[$uKey])) {
                            $userId = $userMap[$uKey];
                        }
                    }

                    // --- Asset Bauen ---
                    $assetData = [
                        'name' => $assetName,
                        'asset_tag' => !empty($assetTag) ? $assetTag : null,
                        'serial' => !empty($serial) ? $serial : null,
                        'model_id' => $modelId,
                        'status_id' => $statusId,
                        'location_id' => $locationId,
                        'user_id' => $userId,
                        'purchase_date' => null,
                        'notes' => 'Importiert am ' . date('d.m.Y')
                    ];

                    try {
                        $matchId = null;
                        $sKey = !empty($serial) ? strtolower(trim($serial)) : '';
                        $tKey = strtolower(trim($assetTag));

                        if (!empty($sKey) && isset($assetSerialMap[$sKey])) {
                            $matchId = $assetSerialMap[$sKey];
                        } elseif (!empty($tKey) && isset($assetTagMap[$tKey])) {
                            $matchId = $assetTagMap[$tKey];
                        }

                        if ($matchId !== null) {
                            if ($assetController->updateAsset($matchId, $assetData)) {
                                $updated++;
                                if (!empty($tKey)) $assetTagMap[$tKey] = $matchId;
                                if (!empty($sKey)) $assetSerialMap[$sKey] = $matchId;
                            } else {
                                $failed++; $report[] = "Zeile $rowCount: DB-Fehler beim Aktualisieren von '$assetTag'.";
                            }
                        } else {
                            if ($assetController->createAsset($assetData)) {
                                $inserted++;
                                $lastId = $db->lastInsertId();
                                if (!empty($tKey)) $assetTagMap[$tKey] = $lastId;
                                if (!empty($sKey)) $assetSerialMap[$sKey] = $lastId;
                            } else {
                                $failed++; $report[] = "Zeile $rowCount: DB-Fehler beim Erstellen von '$assetTag'.";
                            }
                        }
                    } catch (\Exception $e) {
                        if (strpos($e->getMessage(), '1062') !== false) {
                            $failed++; $report[] = "Zeile $rowCount: Duplikat (Seriennummer oder Asset-Tag Konflikt).";
                        } else {
                            $failed++; $report[] = "Zeile $rowCount: " . $e->getMessage();
                        }
                    }
                }
                fclose($handle);
                $success = "Import abgeschlossen. Erstellt: $inserted, Aktualisiert: $updated, Fehlgeschlagen: $failed.";
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
    <title>Assets importieren - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">Mini-Snipe</div>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="assets.php" class="nav-link"><i class="fas fa-laptop"></i> Assets</a>
            <a href="users.php" class="nav-link"><i class="fas fa-users"></i> User</a>
            <?php if (Auth::isAdmin()): ?>
                <a href="locations.php" class="nav-link"><i class="fas fa-map-marker-alt"></i> Standorte</a>
                <a href="settings.php" class="nav-link active"><i class="fas fa-cog"></i> Einstellungen</a>
            <?php endif; ?>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <div>
                <h1>Assets importieren (CSV)</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Lade eine CSV-Datei hoch, um Assets gesammelt anzulegen. Fehlende Modelle/Hersteller werden auto-generiert.</p>
            </div>
            <a href="settings.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-arrow-left"></i> Zurück</a>
        </header>

        <div class="card" style="max-width: 600px; margin-bottom: 2rem;">
            <?php if ($error): ?>
                <div class="alert" style="background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Trennzeichen</label>
                    <select name="delimiter" class="form-control">
                        <option value=";">Semikolon (;)</option>
                        <option value=",">Komma (,)</option>
                        <option value="tab">Tabulator</option>
                    </select>
                </div>

                <div style="margin-bottom: 2rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">CSV Datei auswählen</label>
                    <input type="file" name="csv_file" accept=".csv" required style="display: block; width: 100%; padding: 0.75rem; border: 1px dashed var(--glass-border); border-radius: 0.5rem; background: rgba(0,0,0,0.1); color: var(--text-muted); cursor: pointer;">
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Erwarteter Aufbau: <code>asset_tag; name; model; manufacturer; category; status; location; assigned_to</code></p>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-file-import"></i> Hochladen & Importieren</button>
            </form>
        </div>

        <?php if (!empty($report)): ?>
            <div class="card">
                <h3>Importbericht / Warnungen</h3>
                <ul style="margin-top: 1rem; color: var(--accent-rose); font-size: 0.875rem; list-style: inside;">
                    <?php foreach ($report as $line): ?>
                        <li><?php echo htmlspecialchars($line); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
