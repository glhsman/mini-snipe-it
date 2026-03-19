<?php
require_once __DIR__ . '/../../config/db.php';

// Einstellungen laden, falls noch nicht geschehen
if (!isset($settings)) {
    $db = Database::getInstance();
    $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
}
?>
<div class="top-navbar">
    <button class="hamburger" id="hamburgerBtn" aria-label="Menü öffnen" aria-expanded="false">
        <i class="fas fa-bars"></i>
    </button>
    <a href="index.php" class="brand">
        <?php if ($settings && ($settings['branding_type'] === 'logo' || $settings['branding_type'] === 'logo_text')): ?>
            <?php if (!empty($settings['site_logo'])): ?>
                <img src="<?php echo htmlspecialchars($settings['site_logo']); ?>" alt="Logo">
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($settings && ($settings['branding_type'] === 'text' || $settings['branding_type'] === 'logo_text')): ?>
            <span><?php echo htmlspecialchars($settings['site_name']); ?></span>
        <?php endif; ?>
    </a>
</div>
<script>
(function () {
    var ham = document.getElementById('hamburgerBtn');
    if (!ham) return;

    ham.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = document.body.classList.toggle('sidebar-open');
        ham.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    document.addEventListener('click', function (e) {
        if (!document.body.classList.contains('sidebar-open')) return;
        var sidebar = document.getElementById('mainSidebar');
        if (sidebar && !sidebar.contains(e.target) && !ham.contains(e.target)) {
            document.body.classList.remove('sidebar-open');
            ham.setAttribute('aria-expanded', 'false');
        }
    });
})();
</script>
