<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Helpers\Auth;

Auth::requireEditor();

$db = Database::getInstance();

// Nur den jeweils neuesten Pending-Eintrag pro Code (Seriennummer/Asset-Tag) laden.
$stmt = $db->query("
    SELECT
        i.id,
        i.serial_number,
        i.room_text,
        i.comment_text,
        i.company_name,
        i.captured_at,
        i.created_at,
        (
            SELECT a.name FROM assets a
            WHERE a.archiv_bit = 0
              AND (UPPER(COALESCE(a.serial, '')) = UPPER(i.serial_number)
                   OR UPPER(COALESCE(a.asset_tag, '')) = UPPER(i.serial_number))
            ORDER BY a.id DESC LIMIT 1
        ) AS asset_name,
        (
            SELECT a.asset_tag FROM assets a
            WHERE a.archiv_bit = 0
              AND (UPPER(COALESCE(a.serial, '')) = UPPER(i.serial_number)
                   OR UPPER(COALESCE(a.asset_tag, '')) = UPPER(i.serial_number))
            ORDER BY a.id DESC LIMIT 1
        ) AS matched_asset_tag
    FROM inventory_staging i
    INNER JOIN (
        SELECT MAX(id) AS latest_id
        FROM inventory_staging
        WHERE sync_status = 'pending'
        GROUP BY UPPER(TRIM(serial_number))
    ) latest ON latest.latest_id = i.id
    ORDER BY i.captured_at ASC
");
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($entries);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventur-Prüfung - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .badge-count {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            background: rgba(99, 102, 241, 0.2);
            color: var(--primary-color);
            font-size: 0.875rem;
            font-weight: 600;
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        .status-no-asset { color: #f87171; font-size: 0.8rem; }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'inventory_review'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1><i class="fas fa-clipboard-check"></i> Inventur-Prüfung</h1>
            <span class="badge-count"><?php echo $total; ?> ausstehend</span>
        </header>

        <?php if (isset($_GET['approved'])): ?>
            <div class="alert" style="background: rgba(34,197,94,0.1); color: #4ade80; border: 1px solid rgba(34,197,94,0.2); padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> Alle Einträge wurden überprüft.
            </div>
        <?php endif; ?>

        <?php if ($total === 0): ?>
            <div class="card" style="text-align: center; padding: 3rem;">
                <i class="fas fa-check-circle" style="font-size: 3rem; color: #4ade80; display: block; margin-bottom: 1rem;"></i>
                <p style="color: var(--text-muted);">Keine ausstehenden Inventureinträge vorhanden.</p>
            </div>
        <?php else: ?>
            <div class="card" style="padding: 0; overflow: hidden;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Seriennummer</th>
                            <th>Asset</th>
                            <th>Raum</th>
                            <th>Kommentar</th>
                            <th>Erfasst am</th>
                            <th>Eingegangen</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><code style="font-size: 0.85rem;"><?php echo htmlspecialchars($entry['serial_number']); ?></code></td>
                                <td>
                                    <?php if ($entry['asset_name']): ?>
                                        <strong><?php echo htmlspecialchars($entry['asset_name']); ?></strong><br>
                                        <small style="color: var(--text-muted);"><?php echo htmlspecialchars($entry['matched_asset_tag'] ?? ''); ?></small>
                                    <?php else: ?>
                                        <span class="status-no-asset"><i class="fas fa-exclamation-triangle"></i> Kein Asset gefunden</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($entry['room_text'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($entry['comment_text'] ?? '-'); ?></td>
                                <td><?php echo $entry['captured_at'] ? date('d.m.Y H:i', strtotime($entry['captured_at'])) : '-'; ?></td>
                                <td><?php echo $entry['created_at'] ? date('d.m.Y H:i', strtotime($entry['created_at'])) : '-'; ?></td>
                                <td>
                                    <a href="inventory_review_detail.php?id=<?php echo (int)$entry['id']; ?>" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.9rem;">
                                        <i class="fas fa-search"></i> Prüfen
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
