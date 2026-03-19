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
    if ($userId) {
        $assignmentId = $assetController->checkoutAsset($id, $userId, Auth::getUserId());
        header('Location: user_protocol.php?type=handover&history_id=' . $assignmentId);
        exit;
    }
}

$users = $userController->getAllUsers();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset ausgeben - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-control option, .form-control optgroup { background: #1f2937; color: white; }
        .light-mode .form-control option, .light-mode .form-control optgroup { background: #ffffff; color: #1e293b; }

        /* ===== User-Suchfeld ===== */
        .user-search-wrapper { position: relative; }

        .user-search-input {
            width: 100%;
            padding: 0.65rem 1rem 0.65rem 2.4rem;
            border-radius: 0.5rem;
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            outline: none;
            font-size: 0.9rem;
            transition: border-color 0.2s;
        }
        .user-search-input:focus { border-color: var(--primary-color); }
        .user-search-icon {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.85rem;
            pointer-events: none;
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0; right: 0;
            background: var(--bg-card);
            border: 1px solid var(--glass-border);
            border-radius: 0.5rem;
            max-height: 280px;
            overflow-y: auto;
            z-index: 500;
            display: none;
            backdrop-filter: blur(10px);
            box-shadow: var(--glass-shadow);
        }
        .user-dropdown.open { display: block; }

        .user-dropdown-item {
            padding: 0.6rem 1rem;
            cursor: pointer;
            font-size: 0.875rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
            transition: background 0.15s;
        }
        .user-dropdown-item:last-child { border-bottom: none; }
        .user-dropdown-item:hover,
        .user-dropdown-item.highlighted { background: rgba(99,102,241,0.15); }
        .user-dropdown-item.selected { background: rgba(99,102,241,0.25); color: var(--text-main); }
        .user-dropdown-item .item-username { font-weight: 600; }
        .user-dropdown-item .item-meta { color: var(--text-muted); font-size: 0.8rem; }
        .user-dropdown-item .item-highlight { color: var(--primary-color); font-weight: 700; }
        .user-dropdown-empty { padding: 1rem; color: var(--text-muted); font-size: 0.875rem; text-align: center; }

        /* Anzeige des gewählten Users */
        .user-selected-badge {
            display: none;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            padding: 0.45rem 0.75rem;
            background: rgba(99,102,241,0.12);
            border: 1px solid rgba(99,102,241,0.3);
            border-radius: 0.4rem;
            font-size: 0.85rem;
            color: var(--text-main);
        }
        .user-selected-badge.visible { display: flex; }
        .user-selected-badge .clear-btn {
            margin-left: auto;
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.8rem;
            padding: 0;
            line-height: 1;
        }
        .user-selected-badge .clear-btn:hover { color: var(--accent-rose); }

        /* Light Mode */
        body.light-mode .user-search-input {
            background: #ffffff;
            border-color: rgba(0,0,0,0.15);
            color: #1e293b;
        }
        body.light-mode .user-dropdown {
            background: #ffffff;
            border-color: rgba(0,0,0,0.12);
        }
        body.light-mode .user-dropdown-item { border-bottom-color: rgba(0,0,0,0.05); color: #1e293b; }
        body.light-mode .user-dropdown-item .item-meta { color: #64748b; }
        body.light-mode .user-selected-badge { background: rgba(99,102,241,0.08); }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'assets'; include_once __DIR__ . '/includes/sidebar.php'; ?>

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

                <!-- Hidden input carries the selected user_id on submit -->
                <input type="hidden" name="user_id" id="selectedUserId">

                <!-- Search input + dropdown -->
                <div class="user-search-wrapper" id="userSearchWrapper">
                    <i class="fas fa-search user-search-icon"></i>
                    <input type="text"
                           id="userSearchInput"
                           class="user-search-input"
                           placeholder="Benutzer suchen …"
                           autocomplete="off"
                           aria-label="Benutzer suchen">
                    <div class="user-dropdown" id="userDropdown" role="listbox">
                        <?php foreach ($users as $user): ?>
                            <div class="user-dropdown-item"
                                 role="option"
                                 data-id="<?php echo $user['id']; ?>"
                                 data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                 data-display="<?php echo htmlspecialchars($user['username'] . ' – ' . $user['first_name'] . ' ' . $user['last_name']); ?>"
                                 data-search="<?php echo htmlspecialchars(mb_strtolower($user['username'] . ' ' . $user['first_name'] . ' ' . $user['last_name'])); ?>">
                                <div class="item-username"><?php echo htmlspecialchars($user['username']); ?></div>
                                <div class="item-meta"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?><?php if (!empty($user['location_name'])): ?> · <?php echo htmlspecialchars($user['location_name']); ?><?php endif; ?></div>
                            </div>
                        <?php endforeach; ?>
                        <div class="user-dropdown-empty" id="noResultsMsg" style="display:none;">Keine Ergebnisse</div>
                    </div>
                </div>

                <!-- Badge showing the currently chosen user -->
                <div class="user-selected-badge" id="selectedBadge">
                    <i class="fas fa-user"></i>
                    <span id="selectedLabel"></span>
                    <button type="button" class="clear-btn" id="clearUserBtn" title="Auswahl aufheben"><i class="fas fa-times"></i></button>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;"><i class="fas fa-arrow-right"></i> Zuweisen (Ausgabe)</button>
                    <a href="assets.php" class="btn" style="background: rgba(255,255,255,0.1); flex:1; text-align:center;">Abbrechen</a>
                </div>
            </form>
        </div>
    </main>

<script>
(function () {
    'use strict';

    const input     = document.getElementById('userSearchInput');
    const dropdown  = document.getElementById('userDropdown');
    const hidden    = document.getElementById('selectedUserId');
    const badge     = document.getElementById('selectedBadge');
    const badgeLbl  = document.getElementById('selectedLabel');
    const clearBtn  = document.getElementById('clearUserBtn');
    const noResults = document.getElementById('noResultsMsg');
    const wrapper   = document.getElementById('userSearchWrapper');
    const items     = Array.from(dropdown.querySelectorAll('.user-dropdown-item'));

    let highlightIndex = -1;

    /* ---- helpers ---- */
    function openDropdown() { dropdown.classList.add('open'); }
    function closeDropdown() { dropdown.classList.remove('open'); highlightIndex = -1; }

    function setHighlight(idx) {
        items.forEach(i => i.classList.remove('highlighted'));
        highlightIndex = idx;
        if (idx >= 0 && items[idx]) {
            items[idx].classList.add('highlighted');
            items[idx].scrollIntoView({ block: 'nearest' });
        }
    }

    function selectItem(item) {
        hidden.value   = item.dataset.id;
        badgeLbl.textContent = item.dataset.display;
        badge.classList.add('visible');
        input.value    = '';
        input.placeholder = item.dataset.username + ' (ausgewählt)';
        closeDropdown();
        showAll();
    }

    function clearSelection() {
        hidden.value   = '';
        badge.classList.remove('visible');
        input.placeholder = 'Benutzer suchen …';
        input.value    = '';
        showAll();
        input.focus();
    }

    function showAll() {
        items.forEach(i => i.style.display = '');
        noResults.style.display = 'none';
    }

    /* ---- filter ---- */
    function filter(query) {
        const q = query.toLowerCase().trim();
        let visible = 0;
        items.forEach(item => {
            const match = !q || item.dataset.search.includes(q);
            item.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        noResults.style.display = visible === 0 ? '' : 'none';
        setHighlight(-1);
    }

    /* ---- events ---- */
    input.addEventListener('focus', function () {
        filter(this.value);
        openDropdown();
    });

    input.addEventListener('input', function () {
        filter(this.value);
        openDropdown();
    });

    input.addEventListener('keydown', function (e) {
        const visibleItems = items.filter(i => i.style.display !== 'none');
        if (!dropdown.classList.contains('open')) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const next = Math.min(highlightIndex + 1, visibleItems.length - 1);
            setHighlight(items.indexOf(visibleItems[next]));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (highlightIndex <= 0) { closeDropdown(); return; }
            const prev = Math.max(highlightIndex - 1, 0);
            setHighlight(items.indexOf(visibleItems[prev]));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const highlighted = dropdown.querySelector('.user-dropdown-item.highlighted');
            if (highlighted) selectItem(highlighted);
        } else if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    items.forEach(function (item) {
        item.addEventListener('mousedown', function (e) {
            e.preventDefault(); // keep focus on input
            selectItem(this);
        });
        item.addEventListener('mouseover', function () {
            setHighlight(items.indexOf(this));
        });
    });

    clearBtn.addEventListener('click', clearSelection);

    /* Close when clicking outside */
    document.addEventListener('mousedown', function (e) {
        if (!wrapper.contains(e.target) && !badge.contains(e.target)) {
            closeDropdown();
        }
    });

    /* Form validation: require a selection */
    const form = hidden.closest('form');
    form.addEventListener('submit', function (e) {
        if (!hidden.value) {
            e.preventDefault();
            input.focus();
            openDropdown();
            input.style.borderColor = 'var(--accent-rose, #f43f5e)';
            setTimeout(() => input.style.borderColor = '', 1500);
        }
    });
})();
</script>
</body>
</html>
