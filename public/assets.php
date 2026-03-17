<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireLogin();

$db = Database::getInstance();
$assetController = new AssetController($db);
$masterData = new MasterDataController($db);

$assets = $assetController->getAllAssets();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assets - Mini-Snipe</title>
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
            <a href="logout.php" class="nav-link" style="margin-top: 2rem; border-top: 1px solid var(--glass-border); padding-top: 1.5rem;"><i class="fas fa-sign-out-alt"></i> Abmelden</a>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <h1>Asset Verwaltung</h1>
            <?php if (Auth::isEditor()): ?>
                <div>
                    <a href="asset_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Asset anlegen</a>
                </div>
            <?php endif; ?>
        </header>

        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Tag</th>
                        <th>Seriennummer</th>
                        <th>Modell</th>
                        <th>Hersteller</th>
                        <th>Status</th>
                        <th>Standort</th>
                        <th>Benutzer</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td><?php echo $asset['id']; ?></td>
                        <td><?php echo htmlspecialchars($asset['name']); ?></td>
                        <td><strong><?php echo htmlspecialchars($asset['asset_tag']); ?></strong></td>
                        <td><?php echo htmlspecialchars($asset['serial'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($asset['model_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($asset['manufacturer_name'] ?? '-'); ?></td>
                        <td><span class="badge badge-success"><?php echo htmlspecialchars($asset['status_name']); ?></span></td>
                        <td><?php echo htmlspecialchars($asset['location_name'] ?? '-'); ?></td>
                        <td><?php echo $asset['assigned_to'] ? htmlspecialchars($asset['assigned_to']) : '-'; ?></td>
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

