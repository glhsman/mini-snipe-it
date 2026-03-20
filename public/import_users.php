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
    if ($delimiter === 'tab') {
        $delimiter = "\t";
    }
    $file = $_FILES['csv_file']['tmp_name'];

    if (!is_uploaded_file($file)) {
        $error = "Dateiupload fehlgeschlagen.";
    } else {
        $locations = $masterData->getLocations();
        $locationMap = [];
        foreach ($locations as $loc) {
            $locationMap[strtolower(trim((string) $loc['name']))] = $loc['id'];
        }

        $users = $userController->getAllUsers();
        $userMap = [];
        $emailMap = [];
        $personalnummerMap = [];
        foreach ($users as $u) {
            $usernameKey = strtolower(trim((string) ($u['username'] ?? '')));
            $emailKey = strtolower(trim((string) ($u['email'] ?? '')));
            $personalnummerKey = trim((string) ($u['personalnummer'] ?? ''));

            if ($usernameKey !== '') {
                $userMap[$usernameKey] = $u['id'];
            }
            if ($emailKey !== '') {
                $emailMap[$emailKey] = $u['id'];
            }
            if ($personalnummerKey !== '') {
                $personalnummerMap[$personalnummerKey] = $u['id'];
            }
        }

        if (($handle = fopen($file, "r")) !== false) {
            $header = fgetcsv($handle, 1000, $delimiter, '"', "");

            if (!$header || count($header) < 4) {
                $error = "Ungültiges CSV-Format. Erkannt: " . ($header ? count($header) : 0) . " Spalte(n). "
                    . "Inhalt: '" . htmlspecialchars($header[0] ?? '-') . "'. "
                    . "Tipp: Datei-Trennzeichen prüfen! (Erwartet: username, email, first_name, last_name, location_name, personalnummer, vorgesetzter, status)";
            } else {
                $normalizeHeaderValue = static function ($value): string {
                    $value = trim((string) $value);
                    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
                    $value = strtolower($value);
                    $value = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $value);
                    $value = preg_replace('/[^a-z0-9]+/', '_', $value);
                    return trim((string) $value, '_');
                };

                $normalizedHeader = array_map($normalizeHeaderValue, $header);
                $headerMap = array_flip($normalizedHeader);

                $resolveHeaderIndex = static function (array $headerMap, array $aliases): ?int {
                    foreach ($aliases as $alias) {
                        if (array_key_exists($alias, $headerMap)) {
                            return (int) $headerMap[$alias];
                        }
                    }
                    return null;
                };

                $columnIndexes = [
                    'username' => $resolveHeaderIndex($headerMap, ['username', 'benutzername', 'login', 'user']),
                    'email' => $resolveHeaderIndex($headerMap, ['email', 'e_mail', 'mail']),
                    'first_name' => $resolveHeaderIndex($headerMap, ['first_name', 'firstname', 'vorname']),
                    'last_name' => $resolveHeaderIndex($headerMap, ['last_name', 'lastname', 'nachname']),
                    'location_name' => $resolveHeaderIndex($headerMap, ['location_name', 'location', 'standort', 'standort_name']),
                    'personalnummer' => $resolveHeaderIndex($headerMap, ['personalnummer', 'personal_nr', 'personalnr', 'personnel_number']),
                    'vorgesetzter' => $resolveHeaderIndex($headerMap, ['vorgesetzter', 'vorgesetzte', 'vorgesetzter_name', 'supervisor', 'manager']),
                    'status' => $resolveHeaderIndex($headerMap, ['status', 'is_activ', 'is_active', 'aktiv']),
                ];

                $requiredColumns = ['username', 'email', 'first_name', 'last_name'];
                $missingColumns = array_filter($requiredColumns, static function ($column) use ($columnIndexes) {
                    return $columnIndexes[$column] === null;
                });

                if (!empty($missingColumns)) {
                    $error = "CSV-Kopfzeile unvollständig. Fehlend: " . implode(', ', $missingColumns);
                }
            }

            if (!$error) {
                $getCsvValue = static function (array $row, array $columnIndexes, string $column, string $default = ''): string {
                    $index = $columnIndexes[$column] ?? null;
                    if ($index === null) {
                        return $default;
                    }
                    return trim((string) ($row[$index] ?? $default));
                };

                $rowCount = 0;
                $inserted = 0;
                $updated = 0;
                $failed = 0;

                while (($row = fgetcsv($handle, 1000, $delimiter, '"', "")) !== false) {
                    $rowCount++;
                    if (count($row) < 4) {
                        $failed++;
                        $report[] = "Zeile $rowCount (Zeile im CSV): Unzureichende Spaltenanzahl.";
                        continue;
                    }

                    $username = $getCsvValue($row, $columnIndexes, 'username');
                    $email = $getCsvValue($row, $columnIndexes, 'email');
                    $firstName = $getCsvValue($row, $columnIndexes, 'first_name');
                    $lastName = $getCsvValue($row, $columnIndexes, 'last_name');
                    $locationName = $getCsvValue($row, $columnIndexes, 'location_name');
                    $personalnummer = $getCsvValue($row, $columnIndexes, 'personalnummer');
                    $vorgesetzter = $getCsvValue($row, $columnIndexes, 'vorgesetzter');

                    $statusIndex = $columnIndexes['status'] ?? null;
                    $statusValue = $statusIndex !== null ? trim((string) ($row[$statusIndex] ?? '1')) : '1';

                    if ($username === '') {
                        $failed++;
                        $report[] = "Zeile $rowCount: Benutzername fehlt.";
                        continue;
                    }

                    $locationId = null;
                    if ($locationName !== '') {
                        $lowerLoc = strtolower($locationName);
                        if (isset($locationMap[$lowerLoc])) {
                            $locationId = $locationMap[$lowerLoc];
                        } else {
                            $failed++;
                            $report[] = "Zeile $rowCount: Standort '$locationName' nicht im System vorhanden.";
                            continue;
                        }
                    }

                    $baseUserData = [
                        'username' => $username,
                        'email' => $email !== '' ? $email : null,
                        'first_name' => $firstName !== '' ? $firstName : null,
                        'last_name' => $lastName !== '' ? $lastName : null,
                        'personalnummer' => $personalnummer !== '' ? $personalnummer : null,
                        'vorgesetzter' => $vorgesetzter !== '' ? $vorgesetzter : null,
                        'is_activ' => in_array(strtolower($statusValue), ['0', 'false', 'nein', 'no', 'inaktiv'], true) ? 0 : 1,
                        'location_id' => $locationId,
                    ];

                    try {
                        $uKey = strtolower($username);
                        $eKey = strtolower($email);
                        $pKey = $personalnummer;
                        $userId = null;

                        if ($uKey !== '' && isset($userMap[$uKey])) {
                            $userId = $userMap[$uKey];
                        } elseif ($pKey !== '' && isset($personalnummerMap[$pKey])) {
                            $userId = $personalnummerMap[$pKey];
                        } elseif ($eKey !== '' && isset($emailMap[$eKey])) {
                            $userId = $emailMap[$eKey];
                        }

                        if ($userId !== null) {
                            if ($userController->updateUser($userId, $baseUserData)) {
                                $updated++;
                                $userMap[$uKey] = $userId;
                                if ($eKey !== '') {
                                    $emailMap[$eKey] = $userId;
                                }
                                if ($pKey !== '') {
                                    $personalnummerMap[$pKey] = $userId;
                                }
                            } else {
                                $failed++;
                                $report[] = "Zeile $rowCount: Fehler beim Aktualisieren von '$username'.";
                            }
                        } else {
                            $createUserData = $baseUserData + [
                                'password' => null,
                                'can_login' => 0,
                            ];

                            if ($userController->createUser($createUserData)) {
                                $inserted++;
                                $newUserId = (int) $db->lastInsertId();
                                $userMap[$uKey] = $newUserId;
                                if ($eKey !== '') {
                                    $emailMap[$eKey] = $newUserId;
                                }
                                if ($pKey !== '') {
                                    $personalnummerMap[$pKey] = $newUserId;
                                }
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
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzer importieren - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'settings'; include_once __DIR__ . '/includes/sidebar.php'; ?>

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
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem;">Erwarteter Aufbau: <code>username; email; first_name; last_name; location_name; personalnummer; vorgesetzter; status</code></p>
                    <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem;">Status: <code>1</code> = aktiv, <code>0</code> = inaktiv.</p>
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
