<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetRequestController.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetRequestController;
use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireEditor();

$db = Database::getInstance();
$requestController = new AssetRequestController($db);
$masterData = new MasterDataController($db);

$latestPendingRequestId = 0;
try {
    $latestPendingStmt = $db->query("SELECT COALESCE(MAX(id), 0) FROM asset_requests WHERE status = 'pending'");
    $latestPendingRequestId = (int) $latestPendingStmt->fetchColumn();
} catch (Throwable $e) {
    $latestPendingRequestId = 0;
}

$_SESSION['asset_requests_seen_pending_id'] = $latestPendingRequestId;

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? 'open'));
$locationId = isset($_GET['location_id']) ? (int) $_GET['location_id'] : 0;
$categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;

if (!in_array($status, ['', 'open', 'pending', 'in_progress', 'approved', 'rejected'], true)) {
    $status = 'open';
}

$requests = $requestController->getRequestsFiltered(
    $search,
    $status,
    $locationId > 0 ? $locationId : null,
    $categoryId > 0 ? $categoryId : null,
    300
);

$locations = $masterData->getLocations();
$categories = $masterData->getCategories();

if (!isset($_SESSION['asset_requests_admin_csrf'])) {
    $_SESSION['asset_requests_admin_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['asset_requests_admin_csrf'];

function h($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function requestUserLabel(array $row): string {
    $fullName = trim((string) (($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')));
    if ($fullName === '') {
        return (string) ($row['username'] ?? '-');
    }
    return $fullName . ' (' . (string) ($row['username'] ?? '-') . ')';
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anforderungen - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .request-action-grid {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            align-items: stretch;
        }
        .request-action-grid textarea {
            width: 100%;
            min-width: 0;
            min-height: 72px;
            resize: vertical;
        }
        .status-pill {
            border-radius: 999px;
            padding: 0.2rem 0.55rem;
            font-size: 0.75rem;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
        }
        .status-pending {
            color: #fde68a;
            background: rgba(245, 158, 11, 0.14);
            border-color: rgba(245, 158, 11, 0.32);
        }
        .status-approved {
            color: #86efac;
            background: rgba(34, 197, 94, 0.14);
            border-color: rgba(34, 197, 94, 0.32);
        }
        .status-in-progress {
            color: #93c5fd;
            background: rgba(59, 130, 246, 0.14);
            border-color: rgba(59, 130, 246, 0.32);
        }
        .status-rejected {
            color: #fecaca;
            background: rgba(239, 68, 68, 0.14);
            border-color: rgba(239, 68, 68, 0.32);
        }
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-start;
            flex-wrap: wrap;
        }
        .btn-approve {
            background: rgba(34, 197, 94, 0.22);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: #bbf7d0;
        }
        .btn-progress {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.35);
            color: #bfdbfe;
        }
        .btn-reject {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.35);
            color: #fecaca;
        }
        .action-buttons .btn {
            min-width: 174px;
        }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'asset_requests'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div>
                <h1>Anforderungen</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Eingehende oeffentliche Geraeteanforderungen.</p>
            </div>
        </header>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Status der Anforderung wurde aktualisiert.</div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php if ($_GET['error'] === 'csrf'): ?>
                    Sicherheitspruefung fehlgeschlagen. Bitte erneut versuchen.
                <?php else: ?>
                    Status konnte nicht geaendert werden.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 1.25rem;">
            <form method="GET" style="display:grid; grid-template-columns: minmax(220px, 1fr) repeat(3, minmax(160px, 220px)) auto; gap: 0.75rem; align-items: center;">
                <input type="text" name="q" class="form-control" placeholder="Suche Benutzer, Standort, Kategorie, Begruendung" value="<?php echo h($search); ?>">

                <select name="status" class="form-control">
                    <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>Alle Status</option>
                    <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>Offen</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Neu</option>
                    <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>In Arbeit</option>
                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Erledigt</option>
                    <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Abgelehnt</option>
                </select>

                <select name="location_id" class="form-control">
                    <option value="0">Alle Standorte</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?php echo (int) $location['id']; ?>" <?php echo $locationId === (int) $location['id'] ? 'selected' : ''; ?>>
                            <?php echo h($location['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="category_id" class="form-control">
                    <option value="0">Alle Kategorien</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo (int) $category['id']; ?>" <?php echo $categoryId === (int) $category['id'] ? 'selected' : ''; ?>>
                            <?php echo h($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> Filtern</button>
            </form>
        </div>

        <div class="card">
            <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 1rem; gap: 0.75rem; flex-wrap: wrap;">
                <h2 style="margin:0;">Anforderungsliste</h2>
                <span style="color: var(--text-muted); font-size: 0.875rem;"><?php echo count($requests); ?> Eintraege</span>
            </div>

            <div style="overflow-x:auto;">
                <table class="data-table" style="font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Benutzer</th>
                            <th>Standort</th>
                            <th>Kategorie</th>
                            <th>Anzahl</th>
                            <th>Begruendung</th>
                            <th>Erstellt</th>
                            <th>Status</th>
                            <th>Bearbeitung</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center; color:var(--text-muted);">Keine Anforderungen gefunden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $row): ?>
                            <?php $rowStatus = $requestController->deriveDisplayStatus($row); ?>
                            <tr>
                                <td>#<?php echo (int) $row['id']; ?></td>
                                <td><?php echo h(requestUserLabel($row)); ?></td>
                                <td><?php echo h($row['location_name'] ?? ''); ?></td>
                                <td><?php echo h($row['category_name'] ?? ''); ?></td>
                                <td><?php echo (int) ($row['quantity'] ?? 0); ?></td>
                                <td>
                                    <div style="max-width: 360px; white-space: pre-wrap; word-break: break-word;"><?php echo h($row['reason'] ?? ''); ?></div>
                                    <?php if (!empty($row['internal_note'])): ?>
                                        <div style="margin-top:0.35rem; color: var(--text-muted); font-size:0.79rem;">
                                            <strong>Interne Notiz:</strong> <?php echo h($row['internal_note']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="white-space:nowrap;"><?php echo h($row['requested_at'] ?? ''); ?></td>
                                <td>
                                    <span class="status-pill status-<?php echo h(str_replace('_', '-', $rowStatus)); ?>">
                                        <?php if ($rowStatus === 'approved'): ?>
                                            <i class="fas fa-check"></i> Erledigt
                                        <?php elseif ($rowStatus === 'in_progress'): ?>
                                            <i class="fas fa-spinner"></i> In Arbeit
                                        <?php elseif ($rowStatus === 'rejected'): ?>
                                            <i class="fas fa-times"></i> Abgelehnt
                                        <?php else: ?>
                                            <i class="fas fa-clock"></i> Neu
                                        <?php endif; ?>
                                    </span>
                                    <?php if (!empty($row['processed_by_username'])): ?>
                                        <div style="margin-top:0.35rem; color:var(--text-muted); font-size:0.78rem;">
                                            <?php echo h($row['processed_by_username']); ?>
                                            <?php if (!empty($row['processed_at'])): ?>
                                                · <?php echo h($row['processed_at']); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="min-width: 290px;">
                                    <?php if ($rowStatus === 'pending' || $rowStatus === 'in_progress'): ?>
                                        <form method="POST" action="asset_request_update.php" class="request-action-grid">
                                            <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                            <input type="hidden" name="request_id" value="<?php echo (int) $row['id']; ?>">
                                            <textarea name="internal_note" class="form-control" maxlength="2000" placeholder="Optionale interne Notiz"><?php echo h($requestController->stripInProgressPrefix($row['internal_note'] ?? null) ?? ''); ?></textarea>
                                            <div class="action-buttons">
                                                <?php if ($rowStatus === 'pending'): ?>
                                                    <button class="btn btn-progress" type="submit" name="status" value="in_progress"><i class="fas fa-play"></i> In Arbeit</button>
                                                <?php endif; ?>
                                                <button class="btn btn-approve" type="submit" name="status" value="approved"><i class="fas fa-check"></i> Erledigt</button>
                                                <button class="btn btn-reject" type="submit" name="status" value="rejected"><i class="fas fa-times"></i> Ablehnen</button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 0.8rem;">Bereits abgeschlossen</span>
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
