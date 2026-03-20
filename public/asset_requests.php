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
        .filter-search-group {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }
        .filter-search-group .form-control {
            flex: 1 1 auto;
            min-width: 0;
        }
        .delete-mode-toggle {
            width: 42px;
            min-width: 42px;
            height: 42px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .delete-mode-toggle.is-active {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.35);
            color: #fecaca;
        }
        .request-list-card.delete-mode-off .bulk-toolbar,
        .request-list-card.delete-mode-off .selection-cell {
            display: none;
        }
        .bulk-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        .bulk-toolbar-left {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .bulk-toolbar .form-control {
            min-width: 220px;
        }
        .bulk-delete-button[disabled] {
            opacity: 0.55;
            cursor: not-allowed;
        }
        .selection-cell {
            width: 44px;
            text-align: center;
        }
        .selection-checkbox {
            width: 16px;
            height: 16px;
            accent-color: #dc2626;
            cursor: pointer;
        }
        .selection-checkbox[disabled] {
            cursor: default;
            opacity: 0.4;
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
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success"><?php echo (int) $_GET['deleted']; ?> abgeschlossene Anforderung(en) wurden geloescht.</div>
        <?php endif; ?>
        <?php if (isset($_GET['mail'])): ?>
            <?php if ($_GET['mail'] === 'sent'): ?>
                <div class="alert alert-success">Benachrichtigungs-Mail wurde an den Benutzer versendet.</div>
            <?php elseif ($_GET['mail'] === 'no_email'): ?>
                <div class="alert" style="background: rgba(245, 158, 11, 0.12); color: #facc15; border: 1px solid rgba(245, 158, 11, 0.28);">
                    Keine Benachrichtigungs-Mail versendet: Beim Benutzer ist keine gueltige E-Mail-Adresse hinterlegt.
                </div>
            <?php elseif ($_GET['mail'] === 'failed'): ?>
                <div class="alert" style="background: rgba(239, 68, 68, 0.14); color: #fecaca; border: 1px solid rgba(239, 68, 68, 0.35);">
                    Status wurde gespeichert, aber die Benachrichtigungs-Mail konnte nicht versendet werden.
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php if ($_GET['error'] === 'csrf'): ?>
                    Sicherheitspruefung fehlgeschlagen. Bitte erneut versuchen.
                <?php elseif ($_GET['error'] === 'delete'): ?>
                    Es konnten keine abgeschlossenen Anforderungen geloescht werden.
                <?php else: ?>
                    Status konnte nicht geaendert werden.
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 1.25rem;">
            <form method="GET" style="display:grid; grid-template-columns: minmax(220px, 1fr) repeat(3, minmax(160px, 220px)) auto; gap: 0.75rem; align-items: center;">
                <div class="filter-search-group">
                    <button type="button" class="btn delete-mode-toggle" id="delete-mode-toggle" title="Loeschmodus anzeigen" aria-label="Loeschmodus anzeigen" aria-pressed="false">
                        <i class="fas fa-trash"></i>
                    </button>
                    <input type="text" name="q" class="form-control" placeholder="Suche Benutzer, Standort, Kategorie, Begruendung" value="<?php echo h($search); ?>">
                </div>

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

        <div class="card request-list-card delete-mode-off" id="request-list-card">
            <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 1rem; gap: 0.75rem; flex-wrap: wrap;">
                <h2 style="margin:0;">Anforderungsliste</h2>
                <span style="color: var(--text-muted); font-size: 0.875rem;"><?php echo count($requests); ?> Eintraege</span>
            </div>

            <form method="POST" action="asset_request_update.php" id="bulk-delete-form" class="bulk-toolbar">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <div class="bulk-toolbar-left">
                    <select name="action" id="bulk-action-select" class="form-control">
                        <option value="">Aktion auswaehlen</option>
                        <option value="delete_selected">Auswahl loeschen</option>
                    </select>
                    <button class="btn btn-reject bulk-delete-button" type="submit" id="bulk-delete-button" disabled>
                        <i class="fas fa-trash"></i> Ausfuehren
                    </button>
                </div>
                <span id="bulk-selection-info" style="color: var(--text-muted); font-size: 0.85rem;">Nur erledigte oder abgelehnte Anforderungen koennen geloescht werden.</span>
            </form>

            <div style="overflow-x:auto;">
                <table class="data-table" style="font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th class="selection-cell">
                                <input type="checkbox" id="select-all-completed" class="selection-checkbox" aria-label="Alle abgeschlossenen Anforderungen auswaehlen">
                            </th>
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
                            <td colspan="9" style="text-align:center; color:var(--text-muted);" id="empty-state-cell">Keine Anforderungen gefunden.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $row): ?>
                            <?php $rowStatus = $requestController->deriveDisplayStatus($row); ?>
                            <?php $isCompleted = in_array($rowStatus, ['approved', 'rejected'], true); ?>
                            <tr>
                                <td class="selection-cell">
                                    <input
                                        type="checkbox"
                                        name="request_ids[]"
                                        value="<?php echo (int) $row['id']; ?>"
                                        class="selection-checkbox request-select-checkbox"
                                        form="bulk-delete-form"
                                        <?php echo $isCompleted ? '' : 'disabled'; ?>
                                        aria-label="Anforderung #<?php echo (int) $row['id']; ?> auswaehlen"
                                    >
                                </td>
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
    <script>
        (function () {
            const requestListCard = document.getElementById('request-list-card');
            const deleteModeToggle = document.getElementById('delete-mode-toggle');
            const actionSelect = document.getElementById('bulk-action-select');
            const deleteButton = document.getElementById('bulk-delete-button');
            const selectAll = document.getElementById('select-all-completed');
            const checkboxes = Array.from(document.querySelectorAll('.request-select-checkbox:not([disabled])'));
            const form = document.getElementById('bulk-delete-form');
            const selectionInfo = document.getElementById('bulk-selection-info');
            const emptyStateCell = document.getElementById('empty-state-cell');
            let deleteModeEnabled = false;

            function getSelectedCount() {
                return checkboxes.filter((checkbox) => checkbox.checked).length;
            }

            function updateDeleteModeView() {
                requestListCard.classList.toggle('delete-mode-off', !deleteModeEnabled);
                deleteModeToggle.classList.toggle('is-active', deleteModeEnabled);
                deleteModeToggle.setAttribute('aria-pressed', deleteModeEnabled ? 'true' : 'false');
                deleteModeToggle.setAttribute('title', deleteModeEnabled ? 'Loeschmodus ausblenden' : 'Loeschmodus anzeigen');
                deleteModeToggle.setAttribute('aria-label', deleteModeEnabled ? 'Loeschmodus ausblenden' : 'Loeschmodus anzeigen');

                if (emptyStateCell) {
                    emptyStateCell.colSpan = deleteModeEnabled ? 10 : 9;
                }

                if (!deleteModeEnabled) {
                    actionSelect.value = '';
                    selectAll.checked = false;
                    selectAll.indeterminate = false;
                    checkboxes.forEach((checkbox) => {
                        checkbox.checked = false;
                    });
                }
            }

            function updateBulkControls() {
                const selectedCount = getSelectedCount();
                const deleteSelected = actionSelect.value === 'delete_selected';

                deleteButton.disabled = !(deleteSelected && selectedCount > 0);
                selectAll.checked = checkboxes.length > 0 && selectedCount === checkboxes.length;
                selectAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;

                if (selectedCount > 0) {
                    selectionInfo.textContent = selectedCount + ' abgeschlossene Anforderung(en) ausgewaehlt.';
                } else {
                    selectionInfo.textContent = 'Nur erledigte oder abgelehnte Anforderungen koennen geloescht werden.';
                }
            }

            if (checkboxes.length === 0) {
                selectAll.disabled = true;
            }

            deleteModeToggle.addEventListener('click', function () {
                deleteModeEnabled = !deleteModeEnabled;
                updateDeleteModeView();
                updateBulkControls();
            });
            actionSelect.addEventListener('change', updateBulkControls);
            selectAll.addEventListener('change', function () {
                checkboxes.forEach((checkbox) => {
                    checkbox.checked = selectAll.checked;
                });
                updateBulkControls();
            });

            checkboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', updateBulkControls);
            });

            form.addEventListener('submit', function (event) {
                if (actionSelect.value !== 'delete_selected' || getSelectedCount() === 0) {
                    event.preventDefault();
                    updateBulkControls();
                    return;
                }

                if (!window.confirm('Die ausgewaehlten abgeschlossenen Anforderungen wirklich loeschen?')) {
                    event.preventDefault();
                }
            });

            updateDeleteModeView();
            updateBulkControls();
        })();
    </script>
</body>
</html>
