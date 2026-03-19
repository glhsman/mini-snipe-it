<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireAdmin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: locations.php');
    exit;
}

$locationId = (int)$_GET['id'];
$db = Database::getInstance();
$masterData = new MasterDataController($db);

$location = $masterData->getLocationById($locationId);
if (!$location) {
    header('Location: locations.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'    => $_POST['name'] ?? '',
        'address' => $_POST['address'] ?? '',
        'city'    => $_POST['city'] ?? '',
        'kuerzel' => strtoupper(trim($_POST['kuerzel'] ?? ''))
    ];

    if (empty($data['name'])) {
        $error = "Der Name des Standorts ist ein Pflichtfeld.";
    } elseif (!empty($data['kuerzel']) && !preg_match('/^[A-Z]{2}$/', $data['kuerzel'])) {
        $error = "Das Kürzel muss aus genau 2 Großbuchstaben bestehen.";
    } else {
        if ($masterData->updateLocation($locationId, $data)) {
            header('Location: locations.php?success=' . urlencode('Standort erfolgreich aktualisiert.'));
            exit;
        } else {
            $error = "Fehler beim Aktualisieren des Standorts.";
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
    <title>Standort bearbeiten - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .form-control:focus { border-color: var(--primary-color); }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .alert-error { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'locations'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1>Standort bearbeiten</h1>
            <a href="locations.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-arrow-left"></i> Zurück</a>
        </header>

        <div class="card" style="max-width: 600px;">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Kürzel (2 Großbuchstaben, z.B. MU)</label>
                        <input type="text" name="kuerzel" class="form-control" maxlength="2" pattern="[A-Za-z]{2}" placeholder="z.B. MU" value="<?php echo htmlspecialchars($location['kuerzel'] ?? ''); ?>" style="text-transform: uppercase;" required>
                    </div>
                    <div class="form-group">
                        <label>Name des Standorts (Pflichtfeld)</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($location['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Adresse</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($location['address'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Stadt</label>
                        <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($location['city'] ?? ''); ?>">
                    </div>
                </div>
                <div style="margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Speichern</button>
                    <a href="locations.php" class="btn" style="background: rgba(255,255,255,0.1); margin-left: 10px;">Abbrechen</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
