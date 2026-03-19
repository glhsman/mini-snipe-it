<?php
require_once __DIR__ . '/../../config/db.php';

// Einstellungen laden, falls noch nicht geschehen
if (!isset($settings)) {
    $db = Database::getInstance();
    $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
}
?>
<div class="top-navbar">
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
