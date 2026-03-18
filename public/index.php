<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/DashboardController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Controllers\DashboardController;
use App\Helpers\Auth;

Auth::requireLogin();
$userRole = Auth::getRole();

$db = Database::getInstance();
$assetController = new AssetController($db);
$dashboardController = new DashboardController($db);

$assets = $assetController->getAllAssets();
$stats = $dashboardController->getStats();
$catDistribution = $dashboardController->getCategoryDistribution();
$topManufacturers = $dashboardController->getTopManufacturers(5);

// Letzte 5 Assets für die Tabelle
$recentAssets = array_slice($assets, 0, 5);

$statusClasses = [
    'einsatzbereit' => 'badge-success',
    'ausgegeben' => 'badge-warning',
    'defekt' => 'badge-danger',
    'in reparatur' => 'badge-info',
    'ausgemustert' => 'badge-secondary'
];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mini-Snipe Asset Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="sidebar">
        <div class="logo">Mini-Snipe</div>
        <nav>
            <a href="index.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="assets.php" class="nav-link"><i class="fas fa-laptop"></i> Assets</a>
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
                <h1>Asset Overview</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Willkommen zurück! Hier ist der aktuelle Status deiner Bestände.</p>
            </div>
            <div class="user-profile" style="display: flex; align-items: center;">
                <div class="user-info" style="display: flex; align-items: center; gap: 0.5rem; margin-right: 1.5rem; background: rgba(255,255,255,0.05); padding: 0.5rem 1rem; border-radius: 0.5rem;">
                    <i class="fas fa-user-circle" style="color: var(--primary-color); font-size: 1.15rem;"></i>
                    <span style="font-weight: 600; font-size: 0.875rem; color: var(--text-main);"><?php echo htmlspecialchars(\App\Helpers\Auth::getUsername()); ?></span>
                </div>
                <?php if (Auth::isEditor()): ?>
                    <a href="asset_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Neues Asset</a>
                <?php endif; ?>
                <a href="logout.php" class="btn" style="background: rgba(255,255,255,0.1); margin-left: 10px;"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <div class="stats-grid">
            <a href="assets.php" class="card stats-card" style="text-decoration: none; color: inherit;">
                <div class="card-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;"><i class="fas fa-barcode"></i></div>
                <div class="stats-content">
                    <div class="value"><?php echo $stats['total_assets']; ?></div>
                    <div class="label">Gesamt Assets</div>
                </div>
            </a>
            <a href="settings.php#models" class="card stats-card" style="text-decoration: none; color: inherit;">
                <div class="card-icon" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;"><i class="fas fa-boxes"></i></div>
                <div class="stats-content">
                    <div class="value"><?php echo $stats['total_categories']; ?></div>
                    <div class="label">Gerätetypen</div>
                </div>
            </a>
            <a href="settings.php#manufacturers" class="card stats-card" style="text-decoration: none; color: inherit;">
                <div class="card-icon" style="background: rgba(236, 72, 153, 0.1); color: #ec4899;"><i class="fas fa-industry"></i></div>
                <div class="stats-content">
                    <div class="value"><?php echo $stats['total_manufacturers']; ?></div>
                    <div class="label">Hersteller</div>
                </div>
            </a>
            <a href="users.php" class="card stats-card" style="text-decoration: none; color: inherit;">
                <div class="card-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class="fas fa-users"></i></div>
                <div class="stats-content">
                    <div class="value"><?php echo $stats['total_users']; ?></div>
                    <div class="label">Aktive Benutzer</div>
                </div>
            </a>
        </div>

        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-top: 1.5rem;">
            <a href="assets.php" class="card stats-card" style="text-decoration: none; color: inherit;">
                <div class="card-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald);"><i class="fas fa-check-circle"></i></div>
                <div class="stats-content">
                    <div class="value"><?php echo $stats['status_counts']['Einsatzbereit'] ?? 0; ?></div>
                    <div class="label">Einsatzbereit</div>
                </div>
            </a>
            <a href="assets.php" class="card stats-card" style="text-decoration: none; color: inherit;">
                <div class="card-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;"><i class="fas fa-arrow-circle-right"></i></div>
                <div class="stats-content">
                    <div class="value"><?php echo $stats['status_counts']['Ausgegeben'] ?? 0; ?></div>
                    <div class="label">Ausgegeben</div>
                </div>
            </a>
        </div>

        <div class="charts-grid">
            <div class="card chart-card">
                <h3>Geräteverteilung nach Typ</h3>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            <div class="card chart-card">
                <h3>Top 5 Hersteller</h3>
                <div class="chart-container">
                    <canvas id="manufacturerChart"></canvas>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2>Zuletzt erfasste Assets</h2>
                <a href="assets.php" style="color: var(--primary-color); text-decoration: none; font-size: 0.875rem;">Alle ansehen</a>
            </div>
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
                    <?php if (empty($recentAssets)): ?>
                        <tr><td colspan="6" style="text-align:center; color:var(--text-muted);">Keine Assets vorhanden.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentAssets as $asset): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($asset['asset_tag']); ?></strong></td>
                            <td><?php echo htmlspecialchars($asset['name']); ?></td>
                            <td><?php echo htmlspecialchars($asset['model_name'] ?? 'Unbekannt'); ?></td>
                            <td>
                                <?php 
                                $sName = $asset['status_name'] ?? 'Einsatzbereit';
                                $sClass = $statusClasses[strtolower(trim($sName))] ?? 'badge-success';
                                ?>
                                <span class="badge <?php echo $sClass; ?>"><?php echo htmlspecialchars($sName); ?></span>
                            </td>
                            <td><?php echo $asset['assigned_to'] ? htmlspecialchars($asset['assigned_to']) : '<span style="color:var(--text-muted)">Nicht zugewiesen</span>'; ?></td>
                            <td>
                                <?php if (Auth::isEditor()): ?>
                                    <!-- Ausgabe / Rücknahme -->
                                    <?php if ($asset['assigned_to']): ?>
                                        <form method="POST" action="asset_checkin.php" style="display:inline;">
                                            <input type="hidden" name="id" value="<?php echo $asset['id']; ?>">
                                            <button type="submit" title="Rücknahme (Check-in)" class="btn-icon" style="color: var(--accent-emerald);">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="asset_checkout.php?id=<?php echo $asset['id']; ?>" class="btn-icon" title="Ausgabe (Check-out)" style="color: #a855f7;">
                                            <i class="fas fa-hand-holding"></i>
                                        </a>
                                    <?php endif; ?>

                                    <a href="asset_edit.php?id=<?php echo $asset['id']; ?>" class="btn-icon" title="Bearbeiten">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="asset_delete.php" style="display:inline;" onsubmit="return confirm('Möchten Sie das Asset \'<?php echo htmlspecialchars(addslashes($asset['name'] ?: $asset['asset_tag'])); ?>\' wirklich löschen?');">
                                        <input type="hidden" name="id" value="<?php echo $asset['id']; ?>">
                                        <button type="submit" title="Löschen" class="btn-icon btn-icon-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <i class="fas fa-eye" style="cursor:pointer; color: var(--text-muted);"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
    // Daten vorbereiten
    const catData = <?php echo json_encode($catDistribution); ?>;
    const manData = <?php echo json_encode($topManufacturers); ?>;

    // Balkendiagramm (Kategorien)
    const ctxCat = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(ctxCat, {
        type: 'bar',
        data: {
            labels: catData.map(d => d.name),
            datasets: [{
                label: 'Geräte',
                data: catData.map(d => d.count),
                backgroundColor: 'rgba(99, 102, 241, 0.8)',
                borderColor: '#6366f1',
                borderWidth: 1,
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { color: '#94a3b8' } },
                x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
            },
            plugins: { legend: { display: false } }
        }
    });

    // Ringdiagramm (Hersteller)
    const ctxMan = document.getElementById('manufacturerChart').getContext('2d');
    const manufacturerChart = new Chart(ctxMan, {
        type: 'doughnut',
        data: {
            labels: manData.map(d => d.name),
            datasets: [{
                data: manData.map(d => d.count),
                backgroundColor: [
                    '#6366f1', '#a855f7', '#ec4899', '#f43f5e', '#10b981'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { color: '#94a3b8', usePointStyle: true, padding: 20 } }
            }
        }
    });
    </script>
</body>
</html>
