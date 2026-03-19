<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Helpers\Auth;

Auth::requireEditor();

$db = Database::getInstance();
$assetController = new AssetController($db);

$search = trim((string) ($_GET['q'] ?? ''));
$bookings = $assetController->getAssignmentHistory(250, $search);

function h($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function displayUserName(array $row): string {
    $name = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
    if ($name === '') {
        $name = (string) ($row['username'] ?? '');
    }
    return $name;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buchungen - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'asset_bookings'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div>
                <h1>Asset-Buchungen</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Ausgabe und Ruecknahme pro Benutzer zur Information fuer berechtigte Bearbeiter.</p>
            </div>
        </header>

        <div class="card" style="margin-bottom: 1.5rem;">
            <form method="GET" style="display:grid; grid-template-columns: 1fr auto; gap: 0.75rem; align-items: center;">
                <input
                    type="text"
                    name="q"
                    class="form-control"
                    placeholder="Suche nach Asset-Tag, Seriennummer, Asset-Name oder Benutzer"
                    value="<?php echo h($search); ?>">
                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Suchen</button>
            </form>
        </div>

        <div class="card">
            <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom:1rem;">
                <h2 style="margin:0;">Letzte Buchungen (max. 250)</h2>
                <span style="color: var(--text-muted); font-size: 0.875rem;"><?php echo count($bookings); ?> Eintraege</span>
            </div>

            <div style="overflow-x:auto;">
                <table class="data-table" style="font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th>Buchung</th>
                            <th>Asset</th>
                            <th>Benutzer</th>
                            <th>Ausgabe am</th>
                            <th>Ausgabe durch</th>
                            <th>Ruecknahme am</th>
                            <th>Ruecknahme durch</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; color:var(--text-muted);">Keine Buchungen gefunden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $row): ?>
                        <?php
                            $assetLabel = trim((string) ($row['asset_tag'] ?? ''));
                            if ($assetLabel === '') {
                                $assetLabel = trim((string) ($row['serial'] ?? ''));
                            }
                            if ($assetLabel === '') {
                                $assetLabel = trim((string) ($row['asset_name'] ?? ''));
                            }
                            if ($assetLabel === '') {
                                $assetLabel = 'Asset #' . (int) $row['asset_id'];
                            }
                            $isOpen = empty($row['checkin_at']);
                        ?>
                        <tr>
                            <td>#<?php echo (int) $row['id']; ?></td>
                            <td>
                                <strong><?php echo h($assetLabel); ?></strong>
                                <?php if (!empty($row['model_name'])): ?>
                                    <div style="color:var(--text-muted); font-size:0.8rem;"><?php echo h($row['model_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo h(displayUserName($row)); ?></strong>
                                <div style="color:var(--text-muted); font-size:0.8rem;"><?php echo h($row['username']); ?></div>
                            </td>
                            <td style="white-space:nowrap;"><?php echo h($row['checkout_at']); ?></td>
                            <td><?php echo h($row['checkout_by_username'] ?? ''); ?></td>
                            <td style="white-space:nowrap;"><?php echo h($row['checkin_at'] ?? ''); ?></td>
                            <td><?php echo h($row['checkin_by_username'] ?? ''); ?></td>
                            <td>
                                <?php if ($isOpen): ?>
                                    <span class="badge badge-warning"><i class="fas fa-arrow-right"></i> Ausgegeben</span>
                                <?php else: ?>
                                    <span class="badge badge-success"><i class="fas fa-undo"></i> Zurueckgenommen</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
