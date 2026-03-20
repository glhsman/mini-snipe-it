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

Auth::requireEditor();

$db = Database::getInstance();
$assetController = new AssetController($db);
$masterData = new MasterDataController($db);
$userController = new UserController($db);

$models = $masterData->getAssetModels();
$statusLabels = $masterData->getStatusLabels();
$locations = $masterData->getLocations();
$users = $userController->getAllUsers();
$ramOptions = $masterData->getLookupOptions('ram');
$ssdOptions = $masterData->getLookupOptions('ssd');
$coresOptions = $masterData->getLookupOptions('cores');
$osOptions = $masterData->getLookupOptions('os');


$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serialRequired = $assetController->isSerialNumberRequiredForModel(!empty($_POST['model_id']) ? (int)$_POST['model_id'] : null);
    $data = [
        'asset_tag'     => trim($_POST['asset_tag'] ?? ''),
        'serial'        => trim($_POST['serial'] ?? ''),
        'serial_number_required' => $serialRequired ? 1 : 0,
        'model_id'      => !empty($_POST['model_id']) ? (int)$_POST['model_id'] : null,
        'status_id'     => !empty($_POST['status_id']) ? (int)$_POST['status_id'] : null,
        'location_id'   => !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null,
        'user_id'       => !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null,
        'purchase_date' => !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
        'notes'         => $_POST['notes'] ?? '',
        'name'          => '',
        'pin'           => !empty($_POST['pin']) ? trim($_POST['pin']) : null,
        'puk'           => !empty($_POST['puk']) ? trim($_POST['puk']) : null,
        'rufnummer'     => !empty($_POST['rufnummer']) ? trim($_POST['rufnummer']) : null,
        'mac_adresse'   => !empty($_POST['mac_adresse']) ? trim($_POST['mac_adresse']) : null,
        'ram'           => !empty($_POST['ram']) ? (int)$_POST['ram'] : null,
        'ssd_size'      => !empty($_POST['ssd_size']) ? (int)$_POST['ssd_size'] : null,
        'cores'         => !empty($_POST['cores']) ? (int)$_POST['cores'] : null,
        'os_version'    => !empty($_POST['os_version']) ? (int)$_POST['os_version'] : null
    ];

    if (empty($data['asset_tag'])) {
        $data['asset_tag'] = $assetController->generateAssetTag($data['location_id'], $data['model_id']);
    }

    if (($serialRequired && empty($data['serial'])) || empty($data['status_id'])) {
        $error = $serialRequired
            ? "Seriennummer und Status sind Pflichtfelder."
            : "Status ist ein Pflichtfeld.";
    } else {
        try {
            if ($assetController->createAsset($data)) {
                header('Location: assets.php');
                exit;
            } else {
                $error = "Fehler beim Speichern des Assets.";
            }
        } catch (PDOException $e) {
            // Prüfung, ob Asset Tag bereits existiert (Unique Constraint Verletzung)
            if ($e->getCode() == 23000) {
                $error = "Dieses Asset-Tag existiert bereits.";
            } else {
                $error = "Ein Fehler ist aufgetreten: " . $e->getMessage();
            }
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
    <title>Asset anlegen - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .form-control:focus { border-color: var(--primary-color); }
        .form-control optgroup, .form-control option { background: #1f2937; color: white; }
        .light-mode .form-control optgroup, .light-mode .form-control option { background: #ffffff; color: #1e293b; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .alert-error { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'assets'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1>Asset anlegen</h1>
            <a href="assets.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-arrow-left"></i> Zurück</a>
        </header>

        <div class="card" style="max-width: 800px;">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label id="serialLabel">Seriennummer</label>
                        <input type="text" name="serial" id="serialInput" class="form-control" value="<?php echo isset($_POST['serial']) ? htmlspecialchars($_POST['serial']) : ''; ?>">
                        <small id="serialHint" style="color: var(--text-muted); display: block; margin-top: 0.35rem;"></small>
                    </div>
                    <div class="form-group">
                        <label>Asset-Tag</label>
                        <input type="text" name="asset_tag" class="form-control" placeholder="Wird automatisch generiert falls leer" value="<?php echo isset($_POST['asset_tag']) ? htmlspecialchars($_POST['asset_tag']) : ''; ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Kaufdatum</label>
                        <input type="date" name="purchase_date" class="form-control" style="width: 50%;" value="<?php echo isset($_POST['purchase_date']) ? htmlspecialchars($_POST['purchase_date']) : ''; ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Modell</label>
                        <select name="model_id" class="form-control">
                            <option value="">- Kein Modell -</option>
                            <?php foreach ($models as $model): ?>
                                <option value="<?php echo $model['id']; ?>" 
                                        data-serial-required="<?php echo (int)($model['serial_number_required'] ?? 1); ?>"
                                        data-sim="<?php echo $model['has_sim_fields'] ?? 0; ?>" 
                                        data-hardware="<?php echo $model['has_hardware_fields'] ?? 0; ?>"
                                        <?php echo (isset($_POST['model_id']) && $_POST['model_id'] == $model['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($model['manufacturer_name'] . ' ' . $model['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status (Pflichtfeld)</label>
                        <select name="status_id" class="form-control" required>
                            <option value="">- Status wählen -</option>
                            <?php foreach ($statusLabels as $status): ?>
                                <option value="<?php echo $status['id']; ?>" <?php echo (isset($_POST['status_id']) && $_POST['status_id'] == $status['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Standort</label>
                        <select name="location_id" class="form-control">
                            <option value="">- Kein Standort -</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc['id']; ?>" <?php echo (isset($_POST['location_id']) && $_POST['location_id'] == $loc['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Benutzer zuweisen</label>
                        <select name="user_id" id="user-select" class="form-control">
                            <option value="">- Kein Benutzer -</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo (isset($_POST['user_id']) && $_POST['user_id'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notizen</label>
                    <textarea name="notes" class="form-control" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                </div>

                <!-- Pakete Zusatzfelder -->
                <div id="group-sim" class="form-grid" style="display: none; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem; margin-top: 1.5rem;">
                    <h3 style="grid-column: 1 / -1; margin-bottom: 0.5rem; color: var(--primary-color);">SIM-Daten</h3>
                    <div class="form-group">
                        <label>PIN (4 Ziffern)</label>
                        <input type="text" name="pin" class="form-control" maxlength="4" pattern="\d{4}" placeholder="z.B. 1234" value="<?php echo isset($_POST['pin']) ? htmlspecialchars($_POST['pin']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>PUK (8 Ziffern)</label>
                        <input type="text" name="puk" class="form-control" maxlength="8" pattern="\d{8}" placeholder="z.B. 12345678" value="<?php echo isset($_POST['puk']) ? htmlspecialchars($_POST['puk']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>Rufnummer</label>
                        <input type="text" name="rufnummer" class="form-control" placeholder="z.B. +4915112345678" value="<?php echo isset($_POST['rufnummer']) ? htmlspecialchars($_POST['rufnummer']) : ''; ?>">
                    </div>
                </div>

                <div id="group-hardware" class="form-grid" style="display: none; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.5rem;">
                    <h3 style="grid-column: 1 / -1; margin-bottom: 0.5rem; color: var(--primary-color);">Hardware & OS</h3>
                    <div class="form-group">
                        <label>MAC-Adresse</label>
                        <input type="text" name="mac_adresse" class="form-control" placeholder="z.B. AA:BB:CC:DD:EE:FF" value="<?php echo isset($_POST['mac_adresse']) ? htmlspecialchars($_POST['mac_adresse']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>RAM</label>
                        <select name="ram" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($ramOptions as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>" <?php echo (isset($_POST['ram']) && $_POST['ram'] == $opt['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['value']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>SSD-Größe</label>
                        <select name="ssd_size" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($ssdOptions as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>" <?php echo (isset($_POST['ssd_size']) && $_POST['ssd_size'] == $opt['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['value']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cores (Kerne)</label>
                        <select name="cores" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($coresOptions as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>" <?php echo (isset($_POST['cores']) && $_POST['cores'] == $opt['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['value']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1; width: 50%;">
                        <label>OS / Betriebssystem</label>
                        <select name="os_version" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($osOptions as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>" <?php echo (isset($_POST['os_version']) && $_POST['os_version'] == $opt['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($opt['value']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Speichern</button>
                    <a href="assets.php" class="btn" style="background: rgba(255,255,255,0.1); margin-left: 10px;">Abbrechen</a>
                </div>
            </form>
        </div>
    </main>

    <!-- Select2 für bessere Suchfunktion in Dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <style>
        /* Select2 Styling an Mini-Snipe anpassen */
        .select2-container--default .select2-selection--single {
            background-color: rgba(0,0,0,0.2) !important;
            border: 1px solid var(--glass-border) !important;
            border-radius: 0.5rem !important;
            height: 46px !important; /* Exakt an form-control (0.75rem padding) anpassen */
            color: white !important;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: white !important;
            line-height: normal !important;
            padding-left: 0.75rem !important;
            padding-right: 2rem !important; /* Platz für den Pfeil */
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 100% !important;
            right: 10px !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow b {
            border-color: white transparent transparent transparent !important;
        }
        .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b {
            border-color: transparent transparent white transparent !important;
        }
        .select2-dropdown {
            background-color: #1f2937 !important;
            border: 1px solid var(--glass-border) !important;
            border-radius: 0.5rem !important;
            color: white !important;
            margin-top: 4px; /* Abstand nach unten zum Input */
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
        }
        .select2-search--dropdown {
            padding: 10px !important;
        }
        .select2-search input {
            background-color: rgba(0,0,0,0.4) !important;
            color: white !important;
            border: 1px solid var(--glass-border) !important;
            border-radius: 0.25rem !important;
            padding: 8px !important;
        }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable,
        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: var(--primary-color) !important;
            color: white !important;
        }
        /* Hover-Effekt wie bei normalen form-controls */
        .select2-container--default .select2-selection--single:focus,
        .select2-container--open .select2-selection--single {
            border-color: var(--primary-color) !important;
        }
    </style>

    <script>
        $(document).ready(function() {
            // Select2 für alle Dropdowns aktivieren, damit sie alle exakt gleich aussehen
            $('select.form-control').select2({
                width: '100%'
            });

            // Toggle Zusatzfelder
            function toggleExtraFields() {
                const selected = $('select[name="model_id"]').find(':selected');
                const serialRequired = selected.data('serial-required') != 0;
                const sim = selected.data('sim') == 1;
                const hardware = selected.data('hardware') == 1;
                const serialInput = document.getElementById('serialInput');
                const serialLabel = document.getElementById('serialLabel');
                const serialHint = document.getElementById('serialHint');

                $('#group-sim').toggle(sim);
                $('#group-hardware').toggle(hardware);

                serialInput.required = serialRequired;
                serialInput.readOnly = !serialRequired;
                serialInput.placeholder = serialRequired
                    ? 'Seriennummer eingeben'
                    : 'Wird beim Speichern automatisch erzeugt';
                serialLabel.textContent = serialRequired ? 'Seriennummer (Pflichtfeld)' : 'Seriennummer (automatisch)';
                serialHint.textContent = serialRequired
                    ? ''
                    : 'Bei diesem Modell wird automatisch eine eindeutige NA-Seriennummer erzeugt.';

                if (!serialRequired) {
                    serialInput.value = '';
                }
            }

            $('select[name="model_id"]').on('change', function() {
                toggleExtraFields();
            });

            // Initialer Trigger nach kurzer Verzögerung für Select2 Render
            setTimeout(toggleExtraFields, 300);

            // Seriennummer in Großbuchstaben umwandeln
            const serialInput = document.querySelector('input[name="serial"]');
            if (serialInput) {
                serialInput.addEventListener('input', function() {
                    this.value = this.value.toUpperCase();
                });
            }
        });
    </script>
</body>
</html>
