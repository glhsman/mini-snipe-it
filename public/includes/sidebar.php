<?php
// Set $activePage before including this file.
// Allowed values: 'dashboard', 'assets', 'asset_bookings', 'asset_requests', 'users', 'locations', 'settings', 'settings_general', ''
if (!isset($activePage)) $activePage = '';

$showAssetRequestIndicator = false;

if (\App\Helpers\Auth::isEditor()) {
    require_once __DIR__ . '/../../config/db.php';

    try {
        $db = Database::getInstance();
        $latestPendingStmt = $db->query("SELECT COALESCE(MAX(id), 0) FROM asset_requests WHERE status = 'pending'");
        $latestPendingRequestId = (int) $latestPendingStmt->fetchColumn();
        $seenPendingRequestId = (int) ($_SESSION['asset_requests_seen_pending_id'] ?? 0);
        $showAssetRequestIndicator = $latestPendingRequestId > $seenPendingRequestId;
    } catch (Throwable $e) {
        $showAssetRequestIndicator = false;
    }
}
?>
<script>
(function () {
    if (localStorage.getItem('sidebar_collapsed') === '1') {
        document.body.classList.add('sidebar-collapsed');
    }
})();
</script>
    <div class="sidebar" id="mainSidebar">
        <div class="logo">
            <span class="logo-text">Mini-Snipe</span>
            <button class="sidebar-toggle" id="sidebarToggle" title="Sidebar ein/ausklappen" aria-label="Sidebar ein/ausklappen">
                <i class="fas fa-chevron-left" id="sidebarToggleIcon"></i>
            </button>
        </div>
        <nav>
            <a href="index.php" class="nav-link<?php echo $activePage === 'dashboard' ? ' active' : ''; ?>" title="Dashboard"><i class="fas fa-home"></i><span class="nav-label"> Dashboard</span></a>
            <a href="assets.php" class="nav-link<?php echo $activePage === 'assets' ? ' active' : ''; ?>" title="Assets"><i class="fas fa-laptop"></i><span class="nav-label"> Assets</span></a>
            <?php if (\App\Helpers\Auth::isEditor()): ?>
                <a href="asset_bookings.php" class="nav-link<?php echo $activePage === 'asset_bookings' ? ' active' : ''; ?>" title="Buchungen"><i class="fas fa-exchange-alt"></i><span class="nav-label"> Buchungen</span></a>
                <a href="asset_requests.php" class="nav-link<?php echo $activePage === 'asset_requests' ? ' active' : ''; ?>" title="Anforderungen"><i class="fas fa-clipboard-list"></i><span class="nav-label"> Anforderungen<?php if ($showAssetRequestIndicator): ?><span class="nav-indicator" aria-label="Neue Anforderung vorhanden"></span><?php endif; ?></span></a>
            <?php endif; ?>
            <a href="users.php" class="nav-link<?php echo $activePage === 'users' ? ' active' : ''; ?>" title="User"><i class="fas fa-users"></i><span class="nav-label"> User</span></a>
            <?php if (\App\Helpers\Auth::isAdmin()): ?>
                <a href="locations.php" class="nav-link<?php echo $activePage === 'locations' ? ' active' : ''; ?>" title="Standorte"><i class="fas fa-map-marker-alt"></i><span class="nav-label"> Standorte</span></a>
                <a href="settings.php" class="nav-link<?php echo $activePage === 'settings' ? ' active' : ''; ?>" title="Verwaltung"><i class="fas fa-cog"></i><span class="nav-label"> Verwaltung</span></a>
                <a href="settings_general.php" class="nav-link<?php echo $activePage === 'settings_general' ? ' active' : ''; ?>" title="Einstellungen"><i class="fas fa-sliders-h"></i><span class="nav-label"> Einstellungen</span></a>
            <?php endif; ?>
            <a href="logout.php" class="nav-link nav-logout" title="Abmelden"><i class="fas fa-sign-out-alt"></i><span class="nav-label"> Abmelden</span></a>
        </nav>
    </div>
<script>
(function () {
    var btn = document.getElementById('sidebarToggle');
    var icon = document.getElementById('sidebarToggleIcon');

    function updateIcon() {
        var collapsed = document.body.classList.contains('sidebar-collapsed');
        icon.className = collapsed ? 'fas fa-chevron-right' : 'fas fa-chevron-left';
    }

    updateIcon();

    btn.addEventListener('click', function () {
        document.body.classList.toggle('sidebar-collapsed');
        var collapsed = document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0');
        updateIcon();
    });
})();
</script>
