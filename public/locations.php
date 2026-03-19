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

// GET-Handling für Statusmeldungen
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

$locations = $masterData->getLocations();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standorte - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; }
        .alert-error { background: rgba(244,63,94,0.1); color: var(--accent-rose); border: 1px solid rgba(244,63,94,0.2); }
        .alert-success { background: rgba(16,185,129,0.1); color: var(--accent-emerald); border: 1px solid rgba(16,185,129,0.2); }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'locations'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1>Standortverwaltung</h1>
            <a href="location_create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Standort anlegen</a>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kürzel</th>
                        <th>Name</th>
                        <th>Adresse</th>
                        <th>Stadt</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($locations)): ?>
                        <tr><td colspan="6" style="text-align:center;">Keine Standorte vorhanden.</td></tr>
                    <?php else: ?>
                        <?php foreach ($locations as $location): ?>
                        <tr>
                            <td><?php echo $location['id']; ?></td>
                            <td><span class="badge badge-success" style="font-family:monospace;"><?php echo htmlspecialchars($location['kuerzel'] ?? '-'); ?></span></td>
                            <td><strong><?php echo htmlspecialchars($location['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($location['address'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($location['city'] ?? '-'); ?></td>
                                <td>
                                    <a href="location_edit.php?id=<?php echo $location['id']; ?>" style="color: white; text-decoration: none; margin-right: 15px;"><i class="fas fa-edit"></i></a>
                                    <a href="location_delete.php?id=<?php echo $location['id']; ?>" style="color: var(--accent-rose); text-decoration: none;" onclick="return confirm('Möchten Sie den Standort \'<?php echo addslashes($location['name']); ?>\' wirklich löschen?');"><i class="fas fa-trash"></i></a>
                                </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

</body>
</html>
