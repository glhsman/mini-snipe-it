<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireEditor();

$db = Database::getInstance();
$assetController = new AssetController($db);
$masterData = new MasterDataController($db);

$models = $masterData->getAssetModels();
$locations = $masterData->getLocations();
$statusLabels = $masterData->getStatusLabels();

// Lookup-Werte für Hardware
$ramOptions = $masterData->getLookupOptions('ram');
$ssdOptions = $masterData->getLookupOptions('ssd');
$coresOptions = $masterData->getLookupOptions('cores');
$osOptions = $masterData->getLookupOptions('os');

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assets_json'])) {
    $serialRequired = $assetController->isSerialNumberRequiredForModel((int)($_POST['model_id'] ?? 0));
    $common = [
        'model_id'      => (int)($_POST['model_id'] ?? 0),
        'location_id'   => !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null,
        'status_id'     => (int)($_POST['status_id'] ?? 0),
        'purchase_date' => !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
        'notes'         => $_POST['notes'] ?? '',
        'name'          => '',
        'serial_number_required' => $serialRequired ? 1 : 0,
        'ram'           => !empty($_POST['ram']) ? (int)$_POST['ram'] : null,
        'ssd_size'      => !empty($_POST['ssd_size']) ? (int)$_POST['ssd_size'] : null,
        'cores'         => !empty($_POST['cores']) ? (int)$_POST['cores'] : null,
        'os_version'    => !empty($_POST['os_version']) ? (int)$_POST['os_version'] : null
    ];

    $assets = json_decode($_POST['assets_json'], true);

    if (empty($common['model_id']) || empty($common['status_id'])) {
        $error = "Modell und Status sind Pflichtfelder im Kopfbereich.";
    } elseif (empty($assets)) {
        $error = "Es wurden keine Assets zum Einbuchen hinzugefügt.";
    } else {
        $db->beginTransaction();
        try {
            $successCount = 0;
            foreach ($assets as $a) {
                $data = $common;
                $data['serial'] = strtoupper(trim((string)($a['serial'] ?? '')));
                $data['mac_adresse'] = !empty($a['mac']) ? trim($a['mac']) : null;
                $data['asset_tag'] = !empty($a['inventar']) ? trim($a['inventar']) : '';
                
                if ($serialRequired && empty($data['serial'])) continue;

                if (empty($data['asset_tag'])) {
                    $data['asset_tag'] = $assetController->generateAssetTag($data['location_id'], $data['model_id']);
                }

                if ($assetController->createAsset($data)) {
                    $successCount++;
                }
            }
            $db->commit();
            header('Location: assets.php?success=' . $successCount . ' Assets erfolgreich eingebucht.');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Fehler beim Einbuchen: " . $e->getMessage();
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
    <title>Massen-Einbuchung - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .form-control:focus { border-color: var(--primary-color); }
        .form-control optgroup, .form-control option { background: #1f2937; color: white; }
        .light-mode .form-control optgroup, .light-mode .form-control option { background: #ffffff; color: #1e293b; }
        .alert-error { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }
        .asset-entry-grid { display: grid; grid-template-columns: 2fr 1.5fr 1.5fr auto; gap: 0.75rem; align-items: start; background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 0.5rem; }
        .asset-entry-add-btn { height: 44px; padding: 0 1rem; align-self: end; }
        
        /* Table Styles */
        .batch-table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; background: rgba(0,0,0,0.1); border-radius: 0.5rem; overflow: hidden; }
        .batch-table th, .batch-table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .batch-table th { background: rgba(255,255,255,0.03); font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; }
        .batch-table tr:hover { background: rgba(255,255,255,0.02); }
        
        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 1000; }
        .modal-overlay.active { display: flex; }
        .modal-card { background: #111827; border: 1px solid rgba(255,255,255,0.1); border-radius: 1rem; width: 100%; max-width: 600px; padding: 1.5rem; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .modal-title { font-size: 1.25rem; font-weight: 600; color: white; }
        .close-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem; }

        /* Select2 Styling an Mini-Snipe anpassen */
        .select2-container--default .select2-selection--single {
            background-color: rgba(0,0,0,0.2) !important;
            border: 1px solid var(--glass-border) !important;
            border-radius: 0.5rem !important;
            height: 44px !important;
            color: white !important;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: white !important;
            line-height: normal !important;
            padding-left: 0.75rem !important;
            padding-right: 2rem !important;
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
            margin-top: 4px;
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
        .select2-container--default .select2-selection--single:focus,
        .select2-container--open .select2-selection--single {
            border-color: var(--primary-color) !important;
        }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'assets'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header" style="justify-content: space-between;">
            <h1>Massen-Asset-Einbuchung</h1>
            <a href="assets.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-arrow-left"></i> Zurück</a>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form id="mainForm" method="POST">
            <input type="hidden" name="assets_json" id="assets_json">

            <!-- 1. Kopfdaten -->
            <div class="card" style="margin-bottom: 1.5rem;">
                <h3 style="margin-bottom: 1rem; color: var(--primary-color);">1. Kopfdaten (Für alle Einträge)</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Asset-Modell (Pflichtfeld)</label>
                        <select name="model_id" class="form-control" required style="width: 100%;">
                            <option value="">- Modell wählen -</option>
                            <?php foreach ($models as $model): ?>
                                <option value="<?php echo $model['id']; ?>" 
                                        data-serial-required="<?php echo (int)($model['serial_number_required'] ?? 1); ?>"
                                        data-hardware="<?php echo $model['has_hardware_fields'] ?? 0; ?>">
                                    <?php echo htmlspecialchars($model['manufacturer_name'] . ' ' . $model['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status (Pflichtfeld)</label>
                        <select name="status_id" class="form-control" required style="width: 100%;">
                            <option value="">- Status wählen -</option>
                            <?php foreach ($statusLabels as $status): ?>
                                <option value="<?php echo $status['id']; ?>" <?php echo $status['name'] === 'Einsatzbereit' ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Standort / Gesellschaft</label>
                        <select name="location_id" class="form-control" style="width: 100%;">
                            <option value="">- Kein Standort -</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Einbuchungs-Datum</label>
                        <input type="date" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Notizen / Bemerkung</label>
                    <textarea name="notes" class="form-control" rows="2" placeholder="z.B. Lieferung Desktop PCs 2026"></textarea>
                </div>
            </div>

            <!-- 2. Hardware Optionen (Dynamisch) -->
            <div class="card" id="group-hardware" style="display: none; margin-bottom: 1.5rem;">
                <h3 style="margin-bottom: 1rem; color: var(--primary-color);">2. Hardware-Spezifikationen</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label>RAM</label>
                        <select name="ram" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($ramOptions as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['value']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>SSD-Größe</label>
                        <select name="ssd_size" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($ssdOptions as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['value']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Cores (Kerne)</label>
                        <select name="cores" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($coresOptions as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['value']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>OS / Betriebssystem</label>
                        <select name="os_version" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($osOptions as $opt): ?>
                                <option value="<?php echo $opt['id']; ?>"><?php echo htmlspecialchars($opt['value']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 3. Einzeleingabe -->
            <div class="card">
                <h3 style="margin-bottom: 1rem; color: var(--primary-color);">3. Assets erfassen</h3>
                
                <div class="asset-entry-grid">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label id="input_serial_label" style="margin-bottom: 0.25rem;">Seriennummer (Pflichtfeld)</label>
                        <input type="text" id="input_serial" class="form-control" placeholder="Seriennummer scannen / eintippen" style="text-transform: uppercase;">
                        <small id="input_serial_hint" style="color: var(--text-muted); display: block; margin-top: 0.35rem;"></small>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="margin-bottom: 0.25rem;">MAC-Adresse (Optional)</label>
                        <input type="text" id="input_mac" class="form-control" placeholder="z.B. AA:BB:CC...">
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label style="margin-bottom: 0.25rem;">Inventar-Nr / Asset Tag (Optional)</label>
                        <input type="text" id="input_inventar" class="form-control" placeholder="Falls leer: Auto-Generierung">
                    </div>
                    <button type="button" class="btn btn-primary asset-entry-add-btn" onclick="addAssetRow()"><i class="fas fa-plus"></i></button>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem;">
                    <div style="font-size: 0.875rem; color: var(--text-muted);">
                        Gesamt: <span id="total_count" style="color: white; font-weight: bold;">0</span> Assets
                    </div>
                    <div>
                        <button type="button" id="clipboardButton" class="btn btn-sm" style="background: rgba(255,255,255,0.1); margin-right: 0.5rem;" onclick="openClipboardModal()"><i class="fas fa-paste"></i> Aus Zwischenablage</button>
                        <button type="button" class="btn btn-sm" style="background: rgba(244, 63, 94, 0.2); border: 1px solid rgba(244, 63, 94, 0.3); color: var(--accent-rose);" onclick="clearList()"><i class="fas fa-trash"></i> Liste leeren</button>
                    </div>
                </div>

                <!-- Tabelle -->
                <div style="max-height: 350px; overflow-y: auto; margin-top: 0.75rem; border: 1px solid rgba(255,255,255,0.05); border-radius: 0.5rem;">
                    <table class="batch-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th id="serial_column_header">Seriennummer</th>
                                <th>MAC-Adresse</th>
                                <th>Inventar-Nr / Asset Tag</th>
                                <th style="width: 50px; text-align: center;">Aktion</th>
                            </tr>
                        </thead>
                        <tbody id="batch_tbody">
                            <!-- Dynamisch via JS -->
                        </tbody>
                    </table>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="button" class="btn btn-success" onclick="submitForm()" style="width: 100%; font-size: 1rem; padding: 1rem; background: #22c55e; color: black; font-weight: bold; border: none;"><i class="fas fa-save"></i> In Datenbank Einbuchen</button>
                </div>
            </div>
        </form>
    </main>

    <!-- Modal für Zwischenablage -->
    <div class="modal-overlay" id="clipboardModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title">Aus Zwischenablage importieren</h3>
                <button class="close-btn" onclick="closeClipboardModal()">&times;</button>
            </div>
            <div class="form-group">
                <label id="clipboard_label">Fügen Sie hier Ihre Daten ein (Spalten: Seriennummer [Tab] MAC [Tab] Inventar)</label>
                <textarea id="clipboard_input" class="form-control" rows="10" placeholder="Seriennummer1	MAC1	Inventar1&#10;Seriennummer2	MAC2	Inventar2" style="font-family: monospace; white-space: pre; overflow-x: auto;"></textarea>
            </div>
            <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                <button type="button" class="btn btn-primary" style="flex:1;" onclick="parseClipboard()">Importieren</button>
                <button type="button" class="btn" style="background: rgba(255,255,255,0.1); flex:1;" onclick="closeClipboardModal()">Abbrechen</button>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        let assetsList = [];
        let serialRequired = true;

        $(document).ready(function() {
            // Select2 initialisieren
            $('select.form-control').select2({ width: '100%' });

            // Toggle Hardware-Felder basierend auf Modell
            $('select[name="model_id"]').on('change', function() {
                const selected = $(this).find(':selected');
                serialRequired = selected.data('serial-required') != 0;
                const hardware = selected.data('hardware') == 1;
                $('#group-hardware').toggle(hardware);
                updateSerialInputMode();
            });

            // Enter-Taste im Eingabebereich
            $('#input_serial, #input_mac, #input_inventar').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    addAssetRow();
                }
            });
        });

        function generatePlaceholderSerial() {
            return `NA-${Date.now()}${Math.floor(Math.random() * 900000 + 100000)}`;
        }

        function updateSerialInputMode() {
            const serialInput = $('#input_serial');
            $('#clipboardButton').prop('disabled', !serialRequired).css('opacity', serialRequired ? '1' : '0.5');

            if (serialRequired) {
                $('#input_serial_label').text('Seriennummer (Pflichtfeld)');
                $('#input_serial_hint').text('');
                $('#serial_column_header').text('Seriennummer');
                $('#clipboard_label').text('Fügen Sie hier Ihre Daten ein (Spalten: Seriennummer [Tab] MAC [Tab] Inventar)');
                serialInput.attr('type', 'text');
                serialInput.attr('placeholder', 'Seriennummer scannen / eintippen');
                serialInput.val('');
                serialInput.css('text-transform', 'uppercase');
            } else {
                $('#input_serial_label').text('Stückzahl');
                $('#input_serial_hint').text('Für dieses Modell werden beim Hinzufügen automatisch eindeutige NA-Seriennummern erzeugt.');
                $('#serial_column_header').text('Generierte Seriennummer');
                $('#clipboard_label').text('Zwischenablage-Import ist nur für Modelle mit Seriennummern aktiv.');
                serialInput.attr('type', 'number');
                serialInput.attr('min', '1');
                serialInput.attr('step', '1');
                serialInput.attr('placeholder', 'Anzahl');
                serialInput.val('1');
                serialInput.css('text-transform', 'none');
            }
        }

        function addAssetRow() {
            const rawSerialInput = $('#input_serial').val().trim();
            const mac    = $('#input_mac').val().trim();
            const inventar = $('#input_inventar').val().trim();

            if (serialRequired) {
                const serial = rawSerialInput.toUpperCase();

                if (serial === '') {
                    alert('Seriennummer ist ein Pflichtfeld.');
                    $('#input_serial').focus();
                    return;
                }

                if (assetsList.some(a => a.serial === serial)) {
                    alert('Diese Seriennummer ist bereits in der Liste.');
                    $('#input_serial').select();
                    return;
                }

                assetsList.push({ serial: serial, mac: mac, inventar: inventar });
            } else {
                const quantity = parseInt(rawSerialInput || '0', 10);

                if (!quantity || quantity < 1) {
                    alert('Bitte eine gültige Stückzahl eingeben.');
                    $('#input_serial').focus();
                    return;
                }

                if (quantity > 1 && (mac !== '' || inventar !== '')) {
                    alert('MAC-Adresse oder Inventar-Nr. können bei mehreren Stück nur leer bleiben, damit je Asset eindeutige Werte entstehen.');
                    return;
                }

                for (let i = 0; i < quantity; i++) {
                    let serial;
                    do {
                        serial = generatePlaceholderSerial();
                    } while (assetsList.some(a => a.serial === serial));

                    assetsList.push({ serial: serial, mac: mac, inventar: inventar });
                }
            }
            
            // Felder leeren
            $('#input_serial').val(serialRequired ? '' : '1');
            $('#input_mac').val('');
            $('#input_inventar').val('');
            $('#input_serial').focus();

            renderTable();
        }

        function renderTable() {
            const tbody = $('#batch_tbody');
            tbody.empty();
            $('#total_count').text(assetsList.length);

            if (assetsList.length === 0) {
                tbody.append('<tr><td colspan="5" style="text-align:center; color: var(--text-muted);">Keine Einträge. Erfassen Sie Assets manuell oder nutzen Sie die Zwischenablage, sofern Seriennummern erforderlich sind.</td></tr>');
                return;
            }

            assetsList.forEach((a, index) => {
                const row = `
                    <tr>
                        <td>${index + 1}</td>
                        <td><strong>${a.serial}</strong></td>
                        <td>${a.mac || '-'}</td>
                        <td>${a.inventar || '<span style="color: var(--text-muted); font-size:0.75rem;">Auto-Gen</span>'}</td>
                        <td style="text-align: center;">
                            <button type="button" class="btn btn-sm" style="background:none; border:none; color: var(--accent-rose); cursor:pointer;" onclick="deleteRow(${index})"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }

        function deleteRow(index) {
            assetsList.splice(index, 1);
            renderTable();
        }

        function clearList() {
            if (confirm('Möchten Sie die Liste wirklich leeren?')) {
                assetsList = [];
                renderTable();
            }
        }

        /* Clipboard Logic */
        function openClipboardModal() {
            if (!serialRequired) {
                alert('Der Zwischenablage-Import ist nur für Modelle mit Seriennummern verfügbar.');
                return;
            }
            $('#clipboard_input').val('');
            $('#clipboardModal').addClass('active');
            setTimeout(() => $('#clipboard_input').focus(), 100);
        }

        function closeClipboardModal() {
            $('#clipboardModal').removeClass('active');
        }

        function parseClipboard() {
            const text = $('#clipboard_input').val();
            if (!text) { closeClipboardModal(); return; }

            const lines = text.split(/\r?\n/);
            let added = 0;

            lines.forEach(line => {
                if (!line.trim()) return;

                // Splitting per Tab oder Semikolon (für CSV)
                const parts = line.split(/\t|;/);
                const serial = (parts[0] || '').trim().toUpperCase();
                const mac    = (parts[1] || '').trim();
                const inventar = (parts[2] || '').trim();

                if (serial && !assetsList.some(a => a.serial === serial)) {
                    assetsList.push({ serial: serial, mac: mac, inventar: inventar });
                    added++;
                }
            });

            closeClipboardModal();
            renderTable();
            alert(`${added} Assets importiert.`);
        }

        /* Submit */
        function submitForm() {
            if (assetsList.length === 0) {
                alert('Bitte fügen Sie mindestens ein Asset hinzu.');
                return;
            }

            const modelId = $('select[name="model_id"]').val();
            const statusId = $('select[name="status_id"]').val();

            if (!modelId || !statusId) {
                alert('Bitte wählen Sie Modell und Status im Kopfbericht.');
                return;
            }

            // JSON in Hidden-Feld schreiben
            $('#assets_json').val(JSON.stringify(assetsList));
            $('#mainForm').submit();
        }

        updateSerialInputMode();
        renderTable(); // Initiale Nachricht "Keine Einträge"
    </script>
</body>
</html>
