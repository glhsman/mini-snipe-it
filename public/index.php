<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Helpers\Auth;

Auth::requireLogin();
$userRole = Auth::getRole();

$db = Database::getInstance();
$assetController = new AssetController($db);
$assets = $assetController->getAllAssets();

// Stats berechnen (vereinfacht)
$totalAssets = count($assets);
$deployedAssets = count(array_filter($assets, fn($a) => $a['user_id'] !== null));
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini-Snipe Asset Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">Mini-Snipe</div>
        <nav>
            <a href="index.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="assets.php" class="nav-link"><i class="fas fa-laptop"></i> Assets</a>
            <a href="users.php" class="nav-link"><i class="fas fa-users"></i> User</a>
            <a href="locations.php" class="nav-link"><i class="fas fa-map-marker-alt"></i> Standorte</a>
            <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Einstellungen</a>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <h1>Dashboard</h1>
            <div class="user-profile">
                <?php if (Auth::isEditor()): ?>
                    <a href="assets.php#new" class="btn btn-primary"><i class="fas fa-plus"></i> Neues Asset</a>
                <?php endif; ?>
                <a href="logout.php" class="btn" style="background: rgba(255,255,255,0.1); margin-left: 10px;"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <div class="stats-grid">
            <div class="card stats-card">
                <div class="label">Gesamt Assets</div>
                <div class="value"><?php echo $totalAssets; ?></div>
            </div>
            <div class="card stats-card">
                <div class="label">Zugewiesen</div>
                <div class="value"><?php echo $deployedAssets; ?></div>
            </div>
            <div class="card stats-card">
                <div class="label">Verfügbar</div>
                <div class="value"><?php echo $totalAssets - $deployedAssets; ?></div>
            </div>
        </div>

        <div class="card">
            <h2>Kürzlich hinzugefügte Assets</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Asset Tag</th>
                        <th>Name</th>
                        <th>Modell</th>
                        <th>Status</th>
                        <th>Benutzer</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($assets, 0, 5) as $asset): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($asset['asset_tag']); ?></strong></td>
                        <td><?php echo htmlspecialchars($asset['name']); ?></td>
                        <td><?php echo htmlspecialchars($asset['model_name']); ?></td>
                        <td><span class="badge badge-success"><?php echo htmlspecialchars($asset['status_name']); ?></span></td>
                        <td><?php echo $asset['assigned_to'] ? htmlspecialchars($asset['assigned_to']) : '<span style="color:var(--text-muted)">Nicht zugewiesen</span>'; ?></td>
                        <td>
                            <?php if (Auth::isEditor()): ?>
                                <a href="asset_edit.php?id=<?php echo $asset['id']; ?>" style="color: inherit; text-decoration: none;">
                                    <i class="fas fa-edit" title="Bearbeiten" style="cursor:pointer; margin-right: 10px;"></i>
                                </a>
                                <form method="POST" action="asset_delete.php" style="display:inline;" onsubmit="return confirm('Möchten Sie das Asset \'<?php echo htmlspecialchars(addslashes($asset['name'] ?: $asset['asset_tag'])); ?>\' wirklich löschen?');">
                                    <input type="hidden" name="id" value="<?php echo $asset['id']; ?>">
                                    <button type="submit" title="Löschen" style="background:none; border:none; padding:0; color:var(--accent-rose); cursor:pointer;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <i class="fas fa-eye" style="cursor:pointer; color: var(--text-muted);"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>
