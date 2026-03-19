<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\UserController;
use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireAdmin();

$db = Database::getInstance();
$userController = new UserController($db);
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
        // Locations für Lookup laden
        $locations = $masterData->getLocations();
        $locationMap = [];
        foreach ($locations as $loc) {
            $locationMap[strtolower(trim($loc['name']))] = $loc['id'];
        }

        // User für Lookup laden (für UPDATE statt INSERT bei Duplikaten)
        $users = $userController->getAllUsers();
        $userMap = [];
        foreach ($users as $u) {
            $userMap[strtolower(trim($u['username']))] = $u['id'];
        }

        if (($handle = fopen($file, "r")) !== FALSE) {
            // Kopfzeile überspringen / lesen
            $header = fgetcsv($handle, 1000, $delimiter, '"', "");
            
            if (!$header || count($header) < 4) {
                $error = "Ungültiges CSV-Format. Erkannt: " . ($header ? count($header) : 0) . " Spalte(n). " .
                         "Inhalt: '" . htmlspecialchars($header[0] ?? '-') . "'. " .
                         "Tipp: Datei-Trennzeichen prüfen! (Erwartet: username, email, first_name, last_name)";
            } else {
                $rowCount = 0;
                $inserted = 0;
                $updated = 0;
                $failed = 0;

                while (($row = fgetcsv($handle, 1000, $delimiter, '"', "")) !== FALSE) {
                    $rowCount++;
                    if (count($row) < 4) {
                        $failed++;
                        $report[] = "Zeile $rowCount (Zeile im CSV): Unzureichende Spaltenanzahl.";
                        continue;
                    }

                    $username = trim($row[0]);
                    $email = trim($row[1]);
                    $firstName = trim($row[2]);
                    $lastName = trim($row[3]);
                    $locationName = isset($row[4]) ? trim($row[4]) : '';

                    if (empty($username)) {
                        $failed++;
                        $report[] = "Zeile $rowCount: Benutzername fehlt.";
                        continue;
                    }

                    // Lookup Standort
                    $locationId = null;
                    if (!empty($locationName)) {
                        $lowerLoc = strtolower($locationName);
                        if (isset($locationMap[$lowerLoc])) {
                            $locationId = $locationMap[$lowerLoc];
                        } else {
                            $failed++;
                            $report[] = "Zeile $rowCount: Standort '$locationName' nicht im System vorhanden.";
                            continue;
                        }
                    }

                    // User Daten vorbereiten
                    $userData = [
                        'username'    => $username,
                        'email'       => !empty($email) ? $email : null,
                        'first_name'  => !empty($firstName) ? $firstName : null,
                        'last_name'   => !empty($lastName) ? $lastName : null,
                        'location_id' => $locationId,
                        'password'    => null, // Kein Passwort da Login standardmäßig deaktiviert
                        'can_login'   => 0    // Importierte Benutzer haben standardmäßig KEIN Login-Recht
                    ];

                    try {
                        $uKey = strtolower($username);
                        if (isset($userMap[$uKey])) {
                            // Update
                            $userId = $userMap[$uKey];
                            if ($userController->updateUser($userId, $userData)) {
                                $updated++;
                            } else {
                                $failed++;
                                $report[] = "Zeile $rowCount: Fehler beim Aktualisieren von '$username'.";
                            }
                        } else {
                            // Insert
                            if ($userController->createUser($userData)) {
                                $inserted++;
                                $userMap[$uKey] = $db->lastInsertId();
                            } else {
                                $failed++;
                                $report[] = "Zeile $rowCount: Fehler beim Erstellen von '$username'.";
                            }
                        }
                    } catch (\Exception $e) {
                        $failed++;
                        $report[] = "Zeile $rowCount: " . $e->getMessage();
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
    <title>Benutzer importieren - Mini-Snipe</title>
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
            <?php if (Auth::isAdmin()): ?>
                <a href="locations.php" class="nav-link"><i class="fas fa-map-marker-alt"></i> Standorte</a>
                <a href="settings.php" class="nav-link active"><i class="fas fa-cog"></i> Verwaltung</a>
                <a href="settings_general.php" class="nav-link"><i class="fas fa-sliders-h"></i> Einstellungen</a>
            <?php endif; ?>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <div>
                <h1>Benutzer importieren (CSV)</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Lade eine CSV-Datei hoch, um Benutzer gesammelt anzulegen.</p>
            </div>
            <a href="settings.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-arrow-left"></i> Zurück</a>
        </header>

        <div class="card" style="max-width: 600px; margin-bottom: 2rem;">
            <?php if ($error): ?>
                <div class="alert" style="background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald); border: 1px solid rgba(16, 185, 129, 0.2); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Trennzeichen</label>
                    <select name="delimiter" class="btn" style="background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; width: 100%; text-align:left;">
                        <option value=";">Semikolon (;)</option>
                        <option value=",">Komma (,)</option>
                        <option value="tab">Tabulator</option>
                    </select>
                </div>

                <div style="margin-bottom: 2rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">CSV Datei auswählen</label>
                    <input type="file" name="csv_file" accept=".csv" required style="display: block; width: 100%; padding: 0.75rem; border: 1px dashed var(--glass-border); border-radius: 0.5rem; background: rgba(0,0,0,0.1); color: var(--text-muted); cursor: pointer;">
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Erwarteter Aufbau: <code>username; email; first_name; last_name; location_name</code></p>
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
