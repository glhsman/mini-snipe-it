<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Controllers\MasterDataController;
use App\Helpers\Auth;
use App\Helpers\Settings;

Auth::requireLogin();

$db = Database::getInstance();
\App\Helpers\Settings::load($db);
$assetController = new AssetController($db);
$masterData = new MasterDataController($db);

$allowedPerPage = [25, 50, 100, 250];
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowedPerPage)
    ? (int)$_GET['per_page'] : 25;

$search  = isset($_GET['q'])        ? trim($_GET['q'])        : '';
$modelId = isset($_GET['model_id']) ? (int)$_GET['model_id'] : 0;
$statusId = isset($_GET['status_id']) ? (int)$_GET['status_id'] : 0;
$sort    = isset($_GET['sort'])     ? $_GET['sort']           : 'created_at';
$order   = isset($_GET['order'])    ? $_GET['order']          : 'desc';

// Modelle für Dropdown laden
$models = $masterData->getAssetModels();
$statuses = $masterData->getStatusLabels();

// Fallback: Wenn auf dem Zielsystem noch ein älterer Controller ohne Pagination-Methoden liegt,
// wird serverseitig aus getAllAssets paginiert statt mit Fatal Error abzubrechen.
if (method_exists($assetController, 'countAssetsFiltered') && method_exists($assetController, 'getAssetsPaginatedFiltered')) {
    $totalAssets = $assetController->countAssetsFiltered($search, $modelId ?: null, $statusId ?: null);
    $totalPages  = max(1, (int)ceil($totalAssets / $perPage));
    $page        = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $totalPages)) : 1;
    $offset      = ($page - 1) * $perPage;
    $assets      = $assetController->getAssetsPaginatedFiltered($search, $modelId ?: null, $perPage, $offset, $sort, $order, $statusId ?: null);
} else {
    $allAssets   = $assetController->getAllAssets();
    $allAssets = array_values(array_filter($allAssets, function ($asset) use ($search, $modelId, $statusId) {
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $haystack = mb_strtolower(implode(' ', [
                (string)($asset['asset_tag'] ?? ''),
                (string)($asset['serial'] ?? ''),
                (string)($asset['name'] ?? ''),
            ]));
            if (mb_strpos($haystack, $needle) === false) {
                return false;
            }
        }

        if ($modelId && (int)($asset['model_id'] ?? 0) !== $modelId) {
            return false;
        }

        if ($statusId && (int)($asset['status_id'] ?? 0) !== $statusId) {
            return false;
        }

        return true;
    }));

    $totalAssets = count($allAssets);
    $totalPages  = max(1, (int)ceil($totalAssets / $perPage));
    $page        = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $totalPages)) : 1;
    $offset      = ($page - 1) * $perPage;
    $assets      = array_slice($allAssets, $offset, $perPage);
}

function paginationUrl($p, $pp) {
    $params = $_GET;
    $params['page']     = $p;
    $params['per_page'] = $pp;
    return '?' . http_build_query($params);
}

function sortUrl($field) {
    $params = $_GET;
    $currentSort = $params['sort'] ?? 'created_at';
    $currentOrder = $params['order'] ?? 'desc';
    
    $params['sort'] = $field;
    $params['order'] = ($currentSort === $field && strtolower($currentOrder) === 'asc') ? 'desc' : 'asc';
    return '?' . http_build_query($params);
}

function statusQuickFilterUrl($statusId = null) {
    $params = $_GET;
    $params['page'] = 1;
    if ($statusId) {
        $params['status_id'] = (int)$statusId;
    } else {
        unset($params['status_id']);
    }
    return '?' . http_build_query($params);
}

$currentListUrl = 'assets.php';
if (!empty($_SERVER['QUERY_STRING'])) {
    $currentListUrl .= '?' . $_SERVER['QUERY_STRING'];
}

$statusClasses = [
    'einsatzbereit' => 'badge-success',
    'ausgegeben' => 'badge-warning',
    'defekt' => 'badge-danger',
    'in reparatur' => 'badge-info',
    'ausgemustert' => 'badge-secondary'
];

function renderPagination($page, $totalPages, $perPage) {
    if ($totalPages <= 1) return '';
    ob_start(); ?>
    <div style="display:flex; justify-content:center; align-items:center; gap: 0.4rem; padding: 0.8rem 0;">
        <?php if ($page > 1): ?>
            <a href="<?php echo htmlspecialchars(paginationUrl(1, $perPage)); ?>" style="padding:0.35rem 0.65rem; border-radius:0.375rem; background:rgba(255,255,255,0.07); border:1px solid var(--glass-border); color:var(--text-muted); text-decoration:none; font-size:0.8rem;" title="Erste Seite"><i class="fas fa-angle-double-left"></i></a>
            <a href="<?php echo htmlspecialchars(paginationUrl($page - 1, $perPage)); ?>" style="padding:0.35rem 0.65rem; border-radius:0.375rem; background:rgba(255,255,255,0.07); border:1px solid var(--glass-border); color:var(--text-muted); text-decoration:none; font-size:0.8rem;"><i class="fas fa-angle-left"></i></a>
        <?php endif; ?>

        <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++): ?>
            <a href="<?php echo htmlspecialchars(paginationUrl($i, $perPage)); ?>"
               style="padding:0.35rem 0.65rem; border-radius:0.375rem; font-size:0.8rem; text-decoration:none; min-width:2rem; text-align:center;
                      <?php echo $i === $page ? 'background: var(--primary-color); color: white;' : 'background: rgba(255,255,255,0.07); color: var(--text-muted); border: 1px solid var(--glass-border);'; ?>">
                <?php echo $i; ?>
            </a>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="<?php echo htmlspecialchars(paginationUrl($page + 1, $perPage)); ?>" style="padding:0.35rem 0.65rem; border-radius:0.375rem; background:rgba(255,255,255,0.07); border:1px solid var(--glass-border); color:var(--text-muted); text-decoration:none; font-size:0.8rem;"><i class="fas fa-angle-right"></i></a>
            <a href="<?php echo htmlspecialchars(paginationUrl($totalPages, $perPage)); ?>" style="padding:0.35rem 0.65rem; border-radius:0.375rem; background:rgba(255,255,255,0.07); border:1px solid var(--glass-border); color:var(--text-muted); text-decoration:none; font-size:0.8rem;" title="Letzte Seite"><i class="fas fa-angle-double-right"></i></a>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Settings::getPageTitle('Assets'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'assets'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1>Asset Verwaltung</h1>
            <?php if (Auth::isEditor()): ?>
                <div>
                    <a href="asset_add.php" class="btn btn-primary"><i class="fas fa-plus"></i> Asset anlegen</a>
                    <a href="asset_batch_add.php" class="btn" style="background: rgba(34, 197, 94, 0.2); border: 1px solid rgba(34, 197, 94, 0.3); color: #4ade80; margin-left: 0.5rem;"><i class="fas fa-file-import"></i> Massenimport</a>
                </div>
            <?php endif; ?>
        </header>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Asset erfolgreich gelöscht.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="quick-filter-bar">
                <span class="quick-filter-label">Schnellfilter:</span>

                <a href="<?php echo htmlspecialchars(statusQuickFilterUrl(null)); ?>"
                   class="quick-filter-pill<?php echo !$statusId ? ' is-active' : ''; ?>">
                    Alle
                </a>

                <?php foreach ($statuses as $status): ?>
                    <?php $isActiveQuick = $statusId === (int)$status['id']; ?>
                    <a href="<?php echo htmlspecialchars(statusQuickFilterUrl((int)$status['id'])); ?>"
                       class="quick-filter-pill<?php echo $isActiveQuick ? ' is-active' : ''; ?>">
                        <?php echo htmlspecialchars($status['name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Suchzeile -->
            <form method="GET" action="assets.php" style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap; padding: 1rem 0 1.25rem 0;">
                <!-- Suchfeld (Tag, Serial, Name) -->
                <div style="position:relative; flex:1; min-width:200px;">
                    <i class="fas fa-search" style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Tag, Seriennummer oder Name ..."
                        style="width:100%; padding:0.6rem 0.75rem 0.6rem 2.25rem; border-radius:0.5rem; background:rgba(0,0,0,0.2); border:1px solid var(--glass-border); color:white; outline:none; font-size:0.875rem; box-sizing:border-box;">
                </div>
                <!-- Modell-Filter -->
                <select name="model_id" onchange="this.form.submit()" class="asset-filter-select">
                    <option value="">– Alle Modelle –</option>
                    <?php foreach ($models as $m): ?>
                        <option value="<?php echo $m['id']; ?>" <?php echo $modelId === (int)$m['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($m['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <!-- Status-Schnellfilter -->
                <select name="status_id" onchange="this.form.submit()" class="asset-filter-select">
                    <option value="">– Alle Status –</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?php echo $status['id']; ?>" <?php echo $statusId === (int)$status['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                <button type="submit" class="btn btn-primary" style="padding:0.6rem 1rem; font-size:0.875rem;"><i class="fas fa-filter"></i> Suchen</button>
                <?php if ($search !== '' || $modelId || $statusId): ?>
                    <a href="assets.php?per_page=<?php echo $perPage; ?>" style="padding:0.6rem 0.75rem; border-radius:0.5rem; background:rgba(255,255,255,0.07); border:1px solid var(--glass-border); color:var(--text-muted); text-decoration:none; font-size:0.875rem; white-space:nowrap;">
                        <i class="fas fa-times"></i> Filter zurücksetzen
                    </a>
                <?php endif; ?>
            </form>
            <!-- Zeile: Treffer + Pro-Seite -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; padding-bottom:1rem;">
                <p style="color:var(--text-muted); font-size:0.875rem; margin:0;">
                    <?php $from = $totalAssets ? $offset + 1 : 0; $to = min($offset + $perPage, $totalAssets); ?>
                    Zeige <strong><?php echo $from; ?>&ndash;<?php echo $to; ?></strong> von <strong><?php echo $totalAssets; ?></strong> Assets
                    <?php if ($search !== '' || $modelId || $statusId): ?><span style="color:var(--accent-rose);"> (gefiltert)</span><?php endif; ?>
                </p>
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <span style="color:var(--text-muted); font-size:0.875rem;">Pro Seite:</span>
                    <?php foreach ($allowedPerPage as $opt): ?>
                        <a href="<?php echo htmlspecialchars(paginationUrl(1, $opt)); ?>"
                           style="padding:0.25rem 0.6rem; border-radius:0.375rem; font-size:0.8rem; text-decoration:none;
                                  <?php echo $opt === $perPage ? 'background:var(--primary-color); color:white;' : 'background:rgba(255,255,255,0.07); color:var(--text-muted); border:1px solid var(--glass-border);'; ?>">
                            <?php echo $opt; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <?php echo renderPagination($page, $totalPages, $perPage); ?>
            
            <div style="overflow-x: auto; width: 100%;">
                <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><a href="<?php echo sortUrl('asset_tag'); ?>" style="color:white; text-decoration:none;">Tag <i class="fas <?php echo ($sort === 'asset_tag') ? ($order === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'; ?>" style="font-size:0.75rem; color:rgba(255,255,255,0.4);"></i></a></th>
                        <th>Seriennummer</th>
                        <th><a href="<?php echo sortUrl('model_name'); ?>" style="color:white; text-decoration:none;">Modell <i class="fas <?php echo ($sort === 'model_name') ? ($order === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'; ?>" style="font-size:0.75rem; color:rgba(255,255,255,0.4);"></i></a></th>
                        <th><a href="<?php echo sortUrl('manufacturer_name'); ?>" style="color:white; text-decoration:none;">Hersteller <i class="fas <?php echo ($sort === 'manufacturer_name') ? ($order === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'; ?>" style="font-size:0.75rem; color:rgba(255,255,255,0.4);"></i></a></th>
                        <th><a href="<?php echo sortUrl('status_name'); ?>" style="color:white; text-decoration:none;">Status <i class="fas <?php echo ($sort === 'status_name') ? ($order === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'; ?>" style="font-size:0.75rem; color:rgba(255,255,255,0.4);"></i></a></th>
                        <th><a href="<?php echo sortUrl('location_name'); ?>" style="color:white; text-decoration:none;">Standort <i class="fas <?php echo ($sort === 'location_name') ? ($order === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort'; ?>" style="font-size:0.75rem; color:rgba(255,255,255,0.4);"></i></a></th>
                        <th>Benutzer</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $asset): ?>
                    <tr>
                        <td><?php echo $asset['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($asset['asset_tag'] ?? ''); ?></strong></td>
                        <td><?php echo htmlspecialchars($asset['serial'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($asset['model_name'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($asset['manufacturer_name'] ?? '-'); ?></td>
                        <td>
                            <?php 
                            $sName = $asset['status_name'] ?? 'Einsatzbereit';
                            $sClass = $statusClasses[strtolower(trim($sName))] ?? 'badge-success';
                            ?>
                            <span class="badge <?php echo $sClass; ?>"><?php echo htmlspecialchars($sName); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($asset['location_name'] ?? '-'); ?></td>
                        <td><?php echo $asset['assigned_to'] ? htmlspecialchars($asset['assigned_to']) : '-'; ?></td>
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

                                    <a href="asset_edit.php?id=<?php echo $asset['id']; ?>&amp;return_url=<?php echo urlencode($currentListUrl); ?>" class="btn-icon" title="Bearbeiten">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="asset_delete.php" style="display:inline;" onsubmit="return confirm('Möchten Sie das Asset \'<?php echo htmlspecialchars(addslashes($asset['name'] ?: $asset['asset_tag'])); ?>\' wirklich löschen?');">
                                        <input type="hidden" name="id" value="<?php echo $asset['id']; ?>">
                                        <button type="submit" title="Löschen" class="btn-icon btn-icon-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                            <?php else: ?>
                                <span class="btn-icon" title="Ansehen"><i class="fas fa-eye"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
                   </div>
            
            <?php echo renderPagination($page, $totalPages, $perPage); ?>
            
            <?php if ($totalPages > 1): ?>
                <p style="text-align:center; color:var(--text-muted); font-size:0.8rem; padding-bottom:0.75rem;">Seite <?php echo $page; ?> von <?php echo $totalPages; ?></p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>

