<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireAdmin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: settings.php');
    exit;
}

$id = (int)$_GET['id'];
$db = Database::getInstance();
$masterData = new MasterDataController($db);
$model = $masterData->getAssetModelById($id);

if (!$model) {
    header('Location: settings.php');
    exit;
}

$categories = $masterData->getCategories();
$manufacturers = $masterData->getManufacturers();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'                => $_POST['name'] ?? '',
        'manufacturer_id'     => !empty($_POST['manufacturer_id']) ? (int)$_POST['manufacturer_id'] : null,
        'category_id'         => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
        'model_number'        => $_POST['model_number'] ?? '',
        'serial_number_required' => isset($_POST['serial_number_required']) ? 1 : 0,
        'has_sim_fields'      => isset($_POST['has_sim_fields']) ? 1 : 0,
        'has_hardware_fields' => isset($_POST['has_hardware_fields']) ? 1 : 0
    ];

    if (empty($data['name']) || empty($data['manufacturer_id']) || empty($data['category_id'])) {
        $error = "Name, Hersteller und Kategorie sind Pflichtfelder.";
    } else {
        if ($masterData->updateAssetModel($id, $data)) {
            header('Location: settings.php?success=' . urlencode('Asset-Modell erfolgreich aktualisiert.'));
            exit;
        } else {
            $error = "Fehler beim Aktualisieren des Modells.";
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
    <title>Asset-Modell bearbeiten - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
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
    <?php $activePage = 'settings'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1>Asset-Modell bearbeiten</h1>
            <a href="settings.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-arrow-left"></i> Zurück</a>
        </header>

        <div class="card" style="max-width: 600px;">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Name des Modells (Pflichtfeld)</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($model['name']); ?>" required autofocus>
                    </div>



                    <div class="form-group">
                        <label>Hersteller (Pflichtfeld)</label>
                        <select name="manufacturer_id" class="form-control" required>
                            <option value="">- Bitte wählen -</option>
                            <?php foreach ($manufacturers as $man): ?>
                                <option value="<?php echo $man['id']; ?>" <?php echo ($model['manufacturer_id'] == $man['id'] ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($man['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Kategorie (Pflichtfeld)</label>
                        <select name="category_id" class="form-control" required>
                            <option value="">- Bitte wählen -</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo ($model['category_id'] == $cat['id'] ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Modellnummer</label>
                        <input type="text" name="model_number" class="form-control" placeholder="z.B. A2442" value="<?php echo htmlspecialchars($model['model_number'] ?? ''); ?>">
                    </div>

                    <div class="form-group" style="display: flex; gap: 10px; align-items: center;">
                        <input type="checkbox" name="serial_number_required" id="serial_number_required" value="1" <?php echo ((int)($model['serial_number_required'] ?? 1) === 1) ? 'checked' : ''; ?>>
                        <label for="serial_number_required" style="margin-bottom:0; cursor:pointer;">Seriennummer erforderlich (Ja/Nein)</label>
                    </div>

                    <div class="form-group" style="display: flex; gap: 10px; align-items: center;">
                        <input type="checkbox" name="has_sim_fields" id="has_sim_fields" value="1" <?php echo ($model['has_sim_fields'] == 1) ? 'checked' : ''; ?>>
                        <label for="has_sim_fields" style="margin-bottom:0; cursor:pointer;">Beinhaltet SIM-Daten (PIN, PUK, Rufnummer)</label>
                    </div>

                    <div class="form-group" style="display: flex; gap: 10px; align-items: center;">
                        <input type="checkbox" name="has_hardware_fields" id="has_hardware_fields" value="1" <?php echo ($model['has_hardware_fields'] == 1) ? 'checked' : ''; ?>>
                        <label for="has_hardware_fields" style="margin-bottom:0; cursor:pointer;">Beinhaltet Hardware-Daten (RAM, SSD, Cores, OS)</label>
                    </div>
                </div>

                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Speichern</button>
                    <a href="settings.php" class="btn" style="background: rgba(255,255,255,0.1); margin-left: 10px;">Abbrechen</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
