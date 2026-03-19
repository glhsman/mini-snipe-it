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
$manufacturer = $masterData->getManufacturerById($id);

if (!$manufacturer) {
    header('Location: settings.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';

    if (empty($name)) {
        $error = "Der Name des Herstellers ist ein Pflichtfeld.";
    } else {
        if ($masterData->updateManufacturer($id, $name)) {
            header('Location: settings.php?success=' . urlencode('Hersteller erfolgreich aktualisiert.'));
            exit;
        } else {
            $error = "Fehler beim Aktualisieren des Herstellers.";
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
    <title>Hersteller bearbeiten - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
    <?php $activePage = 'settings'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1>Hersteller bearbeiten</h1>
            <a href="settings.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-arrow-left"></i> Zurück</a>
        </header>

        <div class="card" style="max-width: 600px;">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Name des Herstellers (Pflichtfeld)</label>
                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($manufacturer['name']); ?>" required autofocus>
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
