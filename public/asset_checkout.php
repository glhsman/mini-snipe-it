<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Controllers\UserController;
use App\Helpers\Auth;

Auth::requireEditor();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: assets.php');
    exit;
}

$db = Database::getInstance();
$assetController = new AssetController($db);
$userController = new UserController($db);

$id = (int)$_GET['id'];
$asset = $assetController->getAssetById($id);

if (!$asset) {
    header('Location: assets.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_POST['user_id'] ? (int)$_POST['user_id'] : null;
    
    // Aktuelle Daten holen und user_id anpassen
    $data = [
        'name' => $asset['name'],
        'asset_tag' => $asset['asset_tag'],
        'serial' => $asset['serial'],
        'model_id' => $asset['model_id'],
        'status_id' => $asset['status_id'],
        'location_id' => $asset['location_id'],
        'user_id' => $userId,
        'purchase_date' => $asset['purchase_date'],
        'notes' => $asset['notes']
    ];

    if ($assetController->updateAsset($id, $data)) {
        header('Location: assets.php');
        exit;
    }
}

$users = $userController->getAllUsers();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset ausgeben - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">Mini-Snipe</div>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="assets.php" class="nav-link active"><i class="fas fa-laptop"></i> Assets</a>
            <a href="users.php" class="nav-link"><i class="fas fa-users"></i> User</a>
            <?php if (Auth::isAdmin()): ?>
                <a href="locations.php" class="nav-link"><i class="fas fa-map-marker-alt"></i> Standorte</a>
                <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Einstellungen</a>
            <?php endif; ?>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <div>
                <h1>Asset ausgeben (Checkout)</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Weise das Asset <strong><?php echo htmlspecialchars($asset['name'] ?: $asset['asset_tag']); ?></strong> einem Benutzer zu.</p>
            </div>
            <a href="assets.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-arrow-left"></i> Zurück</a>
        </header>

        <div class="card" style="max-width: 500px;">
            <form method="POST">
                <div style="margin-bottom: 2rem;">
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Benutzer auswählen</label>
                    <select name="user_id" class="form-control" style="width: 100%;" required>
                        <option value="">- Benutzer wählen -</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['username']); ?> (<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-arrow-right"></i> Zuweisen (Ausgabe)</button>
                    <a href="assets.php" class="btn" style="background: rgba(255,255,255,0.1); flex:1; text-align:center;">Abbrechen</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
