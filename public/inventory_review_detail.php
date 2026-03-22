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

function normalizeDateInputValue($value): string {
    if ($value === null) return '';
    $raw = trim((string)$value);
    if ($raw === '' || strpos($raw, '0000-00-00') === 0) return '';
    $formats = ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($formats as $format) {
        $dt = \DateTime::createFromFormat($format, $raw);
        if ($dt instanceof \DateTime) return $dt->format('Y-m-d');
    }
    $ts = strtotime($raw);
    return $ts ? date('Y-m-d', $ts) : '';
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: inventory_review.php');
    exit;
}

$stagingId = (int)$_GET['id'];
$db = Database::getInstance();

// Staging-Eintrag laden
$stagingStmt = $db->prepare("SELECT * FROM inventory_staging WHERE id = :id AND sync_status = 'pending'");
$stagingStmt->execute([':id' => $stagingId]);
$staging = $stagingStmt->fetch(PDO::FETCH_ASSOC);

if (!$staging) {
    header('Location: inventory_review.php');
    exit;
}

$currentCode = trim((string)($staging['serial_number'] ?? ''));

// Falls eine alte Dubletten-Zeile direkt aufgerufen wird, auf die neueste umleiten.
$latestForCodeStmt = $db->prepare("SELECT MAX(id) FROM inventory_staging WHERE sync_status = 'pending' AND UPPER(TRIM(serial_number)) = UPPER(TRIM(:code))");
$latestForCodeStmt->execute([':code' => $currentCode]);
$latestForCodeId = (int)$latestForCodeStmt->fetchColumn();
if ($latestForCodeId > 0 && $latestForCodeId !== $stagingId) {
    header('Location: inventory_review_detail.php?id=' . $latestForCodeId);
    exit;
}

// Nächsten ausstehenden Eintrag ermitteln (dedupliziert auf neueste Zeile je Code)
$nextStmt = $db->prepare("
    SELECT i.id
    FROM inventory_staging i
    INNER JOIN (
        SELECT MAX(id) AS latest_id, UPPER(TRIM(serial_number)) AS code_key
        FROM inventory_staging
        WHERE sync_status = 'pending'
        GROUP BY UPPER(TRIM(serial_number))
    ) latest ON latest.latest_id = i.id
    WHERE latest.code_key <> UPPER(TRIM(:current_code))
    ORDER BY COALESCE(i.captured_at, i.created_at) ASC, i.id ASC
    LIMIT 1
");
$nextStmt->execute([':current_code' => $currentCode]);
$nextId = $nextStmt->fetchColumn();

// Passendes Asset suchen
$assetLookupStmt = $db->prepare("
    SELECT * FROM assets
    WHERE archiv_bit = 0
      AND (UPPER(COALESCE(serial, '')) = UPPER(:serial_code)
           OR UPPER(COALESCE(asset_tag, '')) = UPPER(:tag_code))
    ORDER BY id DESC
    LIMIT 1
");
$assetLookupStmt->execute([
    ':serial_code' => $staging['serial_number'],
    ':tag_code'    => $staging['serial_number'],
]);
$asset = $assetLookupStmt->fetch(PDO::FETCH_ASSOC);
$assetId = $asset ? (int)$asset['id'] : null;

$assetController = new AssetController($db);
$masterData      = new MasterDataController($db);
$userController  = new UserController($db);

$models       = $masterData->getAssetModels();
$statusLabels = $masterData->getStatusLabels();
$locations    = $masterData->getLocations();
$users        = $userController->getAllUsers();
$ramOptions   = $masterData->getLookupOptions('ram');
$ssdOptions   = $masterData->getLookupOptions('ssd');
$coresOptions = $masterData->getLookupOptions('cores');
$osOptions    = $masterData->getLookupOptions('os');

$assetPurchaseDateValue = normalizeDateInputValue($asset['purchase_date'] ?? null);
$assetLastInventurValue = normalizeDateInputValue($asset['last_inventur'] ?? null);

$error   = null;
$success = null;
$createError = null;

$createForm = [
    'serial'        => (string)($_POST['create_serial'] ?? ($staging['serial_number'] ?? '')),
    'asset_tag'     => (string)($_POST['create_asset_tag'] ?? ''),
    'model_id'      => (string)($_POST['create_model_id'] ?? ''),
    'status_id'     => (string)($_POST['create_status_id'] ?? ''),
    'location_id'   => (string)($_POST['create_location_id'] ?? ''),
    'room'          => (string)($_POST['create_room'] ?? ($staging['room_text'] ?? '')),
    'user_id'       => (string)($_POST['create_user_id'] ?? ''),
    'purchase_date' => (string)($_POST['create_purchase_date'] ?? ''),
    'last_inventur' => (string)($_POST['create_last_inventur'] ?? normalizeDateInputValue($staging['captured_at'] ?? null)),
    'notes'         => (string)($_POST['create_notes'] ?? ($staging['comment_text'] ?? '')),
];

// --- POST: Neues Asset direkt aus Inventureintrag anlegen ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_asset' && !$assetId) {
    $modelId = !empty($createForm['model_id']) ? (int)$createForm['model_id'] : null;
    $serialRequired = $assetController->isSerialNumberRequiredForModel($modelId);

    if (($serialRequired && trim($createForm['serial']) === '') || trim($createForm['status_id']) === '') {
        $createError = $serialRequired
            ? 'Seriennummer und Status sind Pflichtfelder.'
            : 'Status ist ein Pflichtfeld.';
    } else {
        $newAssetData = [
            'asset_tag' => trim($createForm['asset_tag']),
            'serial' => trim($createForm['serial']),
            'serial_number_required' => $serialRequired ? 1 : 0,
            'model_id' => $modelId,
            'status_id' => (int)$createForm['status_id'],
            'location_id' => !empty($createForm['location_id']) ? (int)$createForm['location_id'] : null,
            'room' => trim($createForm['room']),
            'user_id' => !empty($createForm['user_id']) ? (int)$createForm['user_id'] : null,
            'purchase_date' => $createForm['purchase_date'] !== '' ? $createForm['purchase_date'] : null,
            'last_inventur' => $createForm['last_inventur'] !== '' ? $createForm['last_inventur'] : null,
            'notes' => $createForm['notes'],
            'name' => '',
            'pin' => null,
            'puk' => null,
            'rufnummer' => null,
            'mac_adresse' => null,
            'ram' => null,
            'ssd_size' => null,
            'cores' => null,
            'os_version' => null,
        ];

        if (empty($newAssetData['asset_tag']) && $assetController->shouldAutoGenerateAssetTag($newAssetData['model_id'])) {
            $newAssetData['asset_tag'] = $assetController->generateAssetTag($newAssetData['location_id'], $newAssetData['model_id']);
        }

        try {
            if ($assetController->createAsset($newAssetData)) {
                $newAssetId = (int)$db->lastInsertId();
                $linkStmt = $db->prepare('UPDATE inventory_staging SET target_asset_id = :asset_id WHERE id = :id');
                $linkStmt->execute([':asset_id' => $newAssetId, ':id' => $stagingId]);

                header('Location: inventory_review_detail.php?id=' . $stagingId . '&created=1');
                exit;
            }
            $createError = 'Fehler beim Anlegen des Assets.';
        } catch (\RuntimeException $e) {
            $createError = $e->getMessage();
        } catch (PDOException $e) {
            $createError = ($e->getCode() == 23000)
                ? 'Dieses Asset-Tag oder diese Seriennummer existiert bereits.'
                : 'Fehler: ' . $e->getMessage();
        }
    }
}

// --- POST: Asset speichern ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_asset' && $assetId) {
    $data = [
        'asset_tag'     => trim($_POST['asset_tag'] ?? ''),
        'serial'        => trim($_POST['serial'] ?? ''),
        'model_id'      => !empty($_POST['model_id']) ? (int)$_POST['model_id'] : null,
        'status_id'     => !empty($_POST['status_id']) ? (int)$_POST['status_id'] : null,
        'location_id'   => !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null,
        'room'          => trim($_POST['room'] ?? ''),
        'user_id'       => !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null,
        'purchase_date' => !empty($_POST['purchase_date']) ? $_POST['purchase_date'] : null,
        'last_inventur' => !empty($_POST['last_inventur']) ? $_POST['last_inventur'] : null,
        'notes'         => $_POST['notes'] ?? '',
        'name'          => $asset['name'] ?? '',
        'pin'           => !empty($_POST['pin']) ? trim($_POST['pin']) : null,
        'puk'           => !empty($_POST['puk']) ? trim($_POST['puk']) : null,
        'rufnummer'     => !empty($_POST['rufnummer']) ? trim($_POST['rufnummer']) : null,
        'mac_adresse'   => !empty($_POST['mac_adresse']) ? trim($_POST['mac_adresse']) : null,
        'ram'           => !empty($_POST['ram']) ? (int)$_POST['ram'] : null,
        'ssd_size'      => !empty($_POST['ssd_size']) ? (int)$_POST['ssd_size'] : null,
        'cores'         => !empty($_POST['cores']) ? (int)$_POST['cores'] : null,
        'os_version'    => !empty($_POST['os_version']) ? (int)$_POST['os_version'] : null,
    ];

    if (empty($data['serial']) || empty($data['status_id'])) {
        $error = "Seriennummer und Status sind Pflichtfelder.";
    } else {
        try {
            if ($assetController->updateAsset($assetId, $data)) {
                $success = "Asset erfolgreich gespeichert.";
                $asset = $assetController->getAssetById($assetId);
                $assetPurchaseDateValue = normalizeDateInputValue($asset['purchase_date'] ?? null);
                $assetLastInventurValue = normalizeDateInputValue($asset['last_inventur'] ?? null);
            }
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        } catch (PDOException $e) {
            $error = ($e->getCode() == 23000)
                ? "Dieses Asset-Tag existiert bereits bei einem anderen Asset."
                : "Fehler: " . $e->getMessage();
        }
    }
}

// --- POST: Inventureintrag als geprüft markieren (löschen) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve') {
    // Alle Pending-Dubletten dieses Codes entfernen, damit kein älterer Eintrag nachrückt.
    $deleteStmt = $db->prepare("DELETE FROM inventory_staging WHERE sync_status = 'pending' AND UPPER(TRIM(serial_number)) = UPPER(TRIM(:code))");
    $deleteStmt->execute([':code' => $currentCode]);

    if ($nextId) {
        header('Location: inventory_review_detail.php?id=' . (int)$nextId);
    } else {
        header('Location: inventory_review.php?approved=1');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventur prüfen - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .review-split {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 1200px) {
            .review-split { grid-template-columns: 1fr; }
        }
        .form-grid   { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
        .form-group  { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .form-control:focus { border-color: var(--primary-color); }
        .form-control optgroup, .form-control option { background: #1f2937; color: white; }
        .light-mode .form-control optgroup, .light-mode .form-control option { background: #ffffff; color: #1e293b; }
        .alert-error   { background: rgba(244,63,94,0.1); color: #f87171; border: 1px solid rgba(244,63,94,0.2); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.25rem; font-size: 0.875rem; }
        .alert-success { background: rgba(34,197,94,0.1); color: #4ade80; border: 1px solid rgba(34,197,94,0.2); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.25rem; font-size: 0.875rem; }
        .field-help  { margin-top: 0.35rem; color: var(--text-muted); font-size: 0.8rem; }
        /* Rechte Panel - Inventurdaten */
        .staging-panel { position: sticky; top: 1.5rem; }
        .staging-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .staging-table tr { border-bottom: 1px solid rgba(255,255,255,0.06); }
        .staging-table tr:last-child { border-bottom: none; }
        .staging-table td { padding: 0.6rem 0.5rem; vertical-align: top; }
        .staging-table td:first-child { color: var(--text-muted); width: 46%; white-space: nowrap; padding-right: 0.75rem; }
        .staging-table td:last-child { word-break: break-word; }
        .staging-value { font-weight: 500; }
        .staging-highlight { color: var(--primary-color); font-weight: 600; }
        /* Approve-Form in Header */
        .header-actions { display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
        .btn-approve { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; border: none; padding: 0.6rem 1.25rem; border-radius: 0.5rem; cursor: pointer; font-size: 0.875rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; }
        .btn-approve:hover { opacity: 0.9; }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'inventory_review'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div>
                <h1><i class="fas fa-search"></i> Inventureintrag prüfen</h1>
                <p style="color: var(--text-muted); font-size: 0.875rem; margin-top: 0.25rem;">
                    Seriennummer: <strong><?php echo htmlspecialchars($staging['serial_number']); ?></strong>
                    <?php if ($nextId): ?>
                        &nbsp;·&nbsp; <span style="color: var(--text-muted);">Weitere Einträge vorhanden</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="header-actions">
                <a href="inventory_review.php" class="btn" style="background: rgba(255,255,255,0.1);">
                    <i class="fas fa-arrow-left"></i> Zurück
                </a>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn-approve"
                            onclick="return confirm('Inventureintrag als geprüft löschen und zum nächsten wechseln?')">
                        <i class="fas fa-check-double"></i> Asset Überprüft &ndash; OK
                    </button>
                </form>
            </div>
        </header>

        <div class="review-split">

            <!-- ============ LINKE SEITE: Asset bearbeiten ============ -->
            <div>
                <?php if ($asset): ?>
                    <div class="card">
                        <h2 style="margin-bottom: 1.25rem; font-size: 1.1rem; color: var(--text-muted);">
                            <i class="fas fa-laptop"></i>
                            &nbsp;<?php echo htmlspecialchars($asset['name'] ?? 'Asset'); ?>
                        </h2>

                        <?php if ($error): ?>
                            <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert-success"><i class="fas fa-check"></i> <?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="action" value="save_asset">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Seriennummer (Pflichtfeld)</label>
                                    <input type="text" name="serial" class="form-control" required value="<?php echo htmlspecialchars($_POST['serial'] ?? $asset['serial'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Asset-Tag</label>
                                    <input type="text" name="asset_tag" class="form-control" placeholder="Automatisch generiert" value="<?php echo htmlspecialchars($_POST['asset_tag'] ?? $asset['asset_tag'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Kaufdatum</label>
                                    <input type="date" name="purchase_date" class="form-control" style="width:50%;" value="<?php echo htmlspecialchars($_POST['purchase_date'] ?? $assetPurchaseDateValue); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Letzte Inventur</label>
                                    <input type="date" name="last_inventur" class="form-control" style="width:50%;" value="<?php echo htmlspecialchars($_POST['last_inventur'] ?? $assetLastInventurValue); ?>">
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Modell</label>
                                    <select name="model_id" id="model-select" class="form-control">
                                        <option value="">- Kein Modell -</option>
                                        <?php foreach ($models as $model):
                                            $selModel = $_POST['model_id'] ?? $asset['model_id']; ?>
                                            <option value="<?php echo $model['id']; ?>"
                                                    data-sim="<?php echo $model['has_sim_fields'] ?? 0; ?>"
                                                    data-hardware="<?php echo $model['has_hardware_fields'] ?? 0; ?>"
                                                    <?php echo ($selModel == $model['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($model['manufacturer_name'] . ' ' . $model['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="field-help">Tippen zum Suchen.</div>
                                </div>
                                <div class="form-group">
                                    <label>Status (Pflichtfeld)</label>
                                    <select name="status_id" class="form-control" required>
                                        <option value="">- Status wählen -</option>
                                        <?php foreach ($statusLabels as $status):
                                            $selStatus = $_POST['status_id'] ?? $asset['status_id']; ?>
                                            <option value="<?php echo $status['id']; ?>" <?php echo ($selStatus == $status['id']) ? 'selected' : ''; ?>>
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
                                        <?php foreach ($locations as $loc):
                                            $selLoc = $_POST['location_id'] ?? $asset['location_id']; ?>
                                            <option value="<?php echo $loc['id']; ?>" <?php echo ($selLoc == $loc['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($loc['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Raum</label>
                                    <input type="text" name="room" class="form-control" placeholder="z.B. Raum 204" value="<?php echo htmlspecialchars($_POST['room'] ?? $asset['room'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Benutzer zuweisen</label>
                                <select name="user_id" id="user-select" class="form-control">
                                    <option value="">- Kein Benutzer -</option>
                                    <?php foreach ($users as $user):
                                        $selUser = $_POST['user_id'] ?? $asset['user_id']; ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo ($selUser == $user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Notizen</label>
                                <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($_POST['notes'] ?? $asset['notes'] ?? ''); ?></textarea>
                            </div>

                            <!-- SIM-Felder -->
                            <div id="group-sim" class="form-grid" style="display:none; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.25rem; margin-top: 0.5rem;">
                                <h3 style="grid-column:1/-1; margin-bottom:0.5rem; color:var(--primary-color);">SIM-Daten</h3>
                                <div class="form-group">
                                    <label>PIN</label>
                                    <input type="text" name="pin" class="form-control" maxlength="4" placeholder="z.B. 1234" value="<?php echo htmlspecialchars($_POST['pin'] ?? $asset['pin'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>PUK</label>
                                    <input type="text" name="puk" class="form-control" maxlength="8" placeholder="z.B. 12345678" value="<?php echo htmlspecialchars($_POST['puk'] ?? $asset['puk'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Rufnummer</label>
                                    <input type="text" name="rufnummer" class="form-control" value="<?php echo htmlspecialchars($_POST['rufnummer'] ?? $asset['rufnummer'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Hardware-Felder -->
                            <div id="group-hardware" class="form-grid" style="display:none; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 1.25rem;">
                                <h3 style="grid-column:1/-1; margin-bottom:0.5rem; color:var(--primary-color);">Hardware & OS</h3>
                                <div class="form-group">
                                    <label>MAC-Adresse</label>
                                    <input type="text" name="mac_adresse" class="form-control" value="<?php echo htmlspecialchars($_POST['mac_adresse'] ?? $asset['mac_adresse'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label>RAM</label>
                                    <select name="ram" class="form-control">
                                        <option value="">- Wählen -</option>
                                        <?php foreach ($ramOptions as $opt): ?>
                                            <option value="<?php echo $opt['id']; ?>" <?php echo (($asset['ram'] ?? '') == $opt['id']) ? 'selected' : ''; ?>>
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
                                            <option value="<?php echo $opt['id']; ?>" <?php echo (($asset['ssd_size'] ?? '') == $opt['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opt['value']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Cores</label>
                                    <select name="cores" class="form-control">
                                        <option value="">- Wählen -</option>
                                        <?php foreach ($coresOptions as $opt): ?>
                                            <option value="<?php echo $opt['id']; ?>" <?php echo (($asset['cores'] ?? '') == $opt['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opt['value']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group" style="grid-column:1/-1; width:50%;">
                                    <label>OS / Betriebssystem</label>
                                    <select name="os_version" class="form-control">
                                        <option value="">- Wählen -</option>
                                        <?php foreach ($osOptions as $opt): ?>
                                            <option value="<?php echo $opt['id']; ?>" <?php echo (($asset['os_version'] ?? '') == $opt['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($opt['value']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div style="margin-top: 1.5rem;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Eigenschaften speichern
                                </button>
                            </div>
                        </form>
                    </div>

                <?php else: ?>
                    <div class="card">
                        <h2 style="margin-bottom: 1.25rem; font-size: 1.05rem; color: #fca5a5;">
                            <i class="fas fa-exclamation-triangle"></i>
                            &nbsp;Kein Asset gefunden - Neues Asset anlegen
                        </h2>

                        <?php if (isset($_GET['created'])): ?>
                            <div class="alert-success"><i class="fas fa-check"></i> Asset wurde erstellt.</div>
                        <?php endif; ?>
                        <?php if ($createError): ?>
                            <div class="alert-error"><?php echo htmlspecialchars($createError); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <input type="hidden" name="action" value="create_asset">

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Seriennummer (Pflichtfeld)</label>
                                    <input type="text" name="create_serial" class="form-control" required value="<?php echo htmlspecialchars($createForm['serial']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Asset-Tag</label>
                                    <input type="text" name="create_asset_tag" class="form-control" placeholder="Automatisch generiert falls leer" value="<?php echo htmlspecialchars($createForm['asset_tag']); ?>">
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Modell</label>
                                    <select name="create_model_id" class="form-control" id="create-model-select">
                                        <option value="">- Kein Modell -</option>
                                        <?php foreach ($models as $model): ?>
                                            <option value="<?php echo $model['id']; ?>" <?php echo ((string)$createForm['model_id'] === (string)$model['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($model['manufacturer_name'] . ' ' . $model['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Status (Pflichtfeld)</label>
                                    <select name="create_status_id" class="form-control" required>
                                        <option value="">- Status wählen -</option>
                                        <?php foreach ($statusLabels as $status): ?>
                                            <option value="<?php echo $status['id']; ?>" <?php echo ((string)$createForm['status_id'] === (string)$status['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($status['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Standort</label>
                                    <select name="create_location_id" class="form-control">
                                        <option value="">- Kein Standort -</option>
                                        <?php foreach ($locations as $loc): ?>
                                            <option value="<?php echo $loc['id']; ?>" <?php echo ((string)$createForm['location_id'] === (string)$loc['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($loc['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Raum</label>
                                    <input type="text" name="create_room" class="form-control" value="<?php echo htmlspecialchars($createForm['room']); ?>">
                                </div>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Kaufdatum</label>
                                    <input type="date" name="create_purchase_date" class="form-control" style="width:50%;" value="<?php echo htmlspecialchars($createForm['purchase_date']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Letzte Inventur</label>
                                    <input type="date" name="create_last_inventur" class="form-control" style="width:50%;" value="<?php echo htmlspecialchars($createForm['last_inventur']); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Benutzer zuweisen</label>
                                <select name="create_user_id" class="form-control" id="create-user-select">
                                    <option value="">- Kein Benutzer -</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo $user['id']; ?>" <?php echo ((string)$createForm['user_id'] === (string)$user['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Notizen</label>
                                <textarea name="create_notes" class="form-control" rows="3"><?php echo htmlspecialchars($createForm['notes']); ?></textarea>
                            </div>

                            <div style="margin-top: 1.25rem; display: flex; gap: 0.75rem; align-items: center;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Asset jetzt anlegen
                                </button>
                                <span style="color: var(--text-muted); font-size: 0.82rem;">Daten sind aus dem Scan vorausgefüllt.</span>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ============ RECHTE SEITE: Inventurdaten ============ -->
            <div class="staging-panel">
                <div class="card">
                    <h2 style="margin-bottom: 1.25rem; font-size: 1rem; color: var(--text-muted);">
                        <i class="fas fa-mobile-alt"></i> &nbsp;Inventurdaten (Scan)
                    </h2>
                    <table class="staging-table">
                        <tr>
                            <td>Seriennummer</td>
                            <td><span class="staging-highlight"><?php echo htmlspecialchars($staging['serial_number']); ?></span></td>
                        </tr>
                        <tr>
                            <td>Raum</td>
                            <td><span class="staging-value"><?php echo htmlspecialchars($staging['room_text'] ?? '–'); ?></span></td>
                        </tr>
                        <tr>
                            <td>Kommentar</td>
                            <td><?php echo htmlspecialchars($staging['comment_text'] ?? '–'); ?></td>
                        </tr>
                        <tr>
                            <td>Firma</td>
                            <td><?php echo htmlspecialchars($staging['company_name'] ?? '–'); ?></td>
                        </tr>
                        <tr>
                            <td>Erfasst am</td>
                            <td><?php echo $staging['captured_at'] ? date('d.m.Y H:i', strtotime($staging['captured_at'])) : '–'; ?></td>
                        </tr>
                        <tr>
                            <td>Eingegangen am</td>
                            <td><?php echo $staging['created_at'] ? date('d.m.Y H:i', strtotime($staging['created_at'])) : '–'; ?></td>
                        </tr>
                        <tr>
                            <td>Client-ID</td>
                            <td style="font-size:0.78rem; word-break:break-all; color:var(--text-muted);"><?php echo htmlspecialchars($staging['client_id']); ?></td>
                        </tr>
                        <?php if ($staging['company_id']): ?>
                        <tr>
                            <td>Firmen-ID</td>
                            <td><?php echo (int)$staging['company_id']; ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <?php if ($asset): ?>
                        <hr style="border-color: rgba(255,255,255,0.07); margin: 1.25rem 0;">
                        <h3 style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.75rem;">Aktueller Asset-Stand</h3>
                        <table class="staging-table">
                            <tr>
                                <td>Name</td>
                                <td><?php echo htmlspecialchars($asset['name'] ?? '–'); ?></td>
                            </tr>
                            <tr>
                                <td>Asset-Tag</td>
                                <td><?php echo htmlspecialchars($asset['asset_tag'] ?? '–'); ?></td>
                            </tr>
                            <tr>
                                <td>Aktuell: Raum</td>
                                <td><?php echo htmlspecialchars($asset['room'] ?? '–'); ?></td>
                            </tr>
                            <tr>
                                <td>Letzte Inventur</td>
                                <td><?php echo !empty($asset['last_inventur']) ? date('d.m.Y H:i', strtotime($asset['last_inventur'])) : '–'; ?></td>
                            </tr>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /review-split -->
    </main>

    <!-- Select2 für Dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <style>
        .select2-container--default .select2-selection--single {
            background-color: rgba(0,0,0,0.2) !important; border: 1px solid var(--glass-border) !important;
            border-radius: 0.5rem !important; height: 46px !important; color: white !important;
            display: flex; align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered { color: white !important; padding-left: 0.75rem !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 100% !important; right: 10px !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow b { border-color: white transparent transparent transparent !important; }
        .select2-container--default.select2-container--open .select2-selection--single .select2-selection__arrow b { border-color: transparent transparent white transparent !important; }
        .select2-dropdown { background-color: #1f2937 !important; border: 1px solid var(--glass-border) !important; border-radius: 0.5rem !important; }
        .select2-search input { background-color: rgba(0,0,0,0.4) !important; color: white !important; border: 1px solid var(--glass-border) !important; border-radius: 0.25rem !important; padding: 8px !important; }
        .select2-container--default .select2-results__option--highlighted.select2-results__option--selectable { background-color: var(--primary-color) !important; }
    </style>
    <script>
    $(document).ready(function () {
        $('select.form-control').not('#model-select').select2({ width: '100%' });
        $('#model-select').select2({ width: '100%', placeholder: 'Modell suchen...', allowClear: true });

        function toggleExtraFields() {
            var selected = $('select[name="model_id"]').find(':selected');
            $('#group-sim').toggle(selected.data('sim') == 1);
            $('#group-hardware').toggle(selected.data('hardware') == 1);
        }
        $('select[name="model_id"]').on('change', toggleExtraFields);
        setTimeout(toggleExtraFields, 300);

        $('input[name="serial"]').on('input', function () {
            this.value = this.value.toUpperCase();
        });
    });
    </script>
</body>
</html>
