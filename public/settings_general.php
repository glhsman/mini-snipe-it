<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Helpers\Auth;

Auth::requireAdmin();

/**
 * Verkleinert ein Bild auf eine maximale Höhe, falls nötig und GD verfügbar ist.
 */
function resizeImage($sourceFile, $targetFile, $maxHeight = 100) {
    if (!extension_loaded('gd')) {
        return move_uploaded_file($sourceFile, $targetFile);
    }

    $info = getimagesize($sourceFile);
    if (!$info) return false;

    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];

    if ($height <= $maxHeight) {
        return move_uploaded_file($sourceFile, $targetFile);
    }

    $ratio = $width / $height;
    $newHeight = $maxHeight;
    $newWidth = round($newHeight * $ratio);

    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($sourceFile); break;
        case 'image/png': $src = @imagecreatefrompng($sourceFile); break;
        case 'image/gif': $src = @imagecreatefromgif($sourceFile); break;
        case 'image/webp': $src = @imagecreatefromwebp($sourceFile); break;
        default: return move_uploaded_file($sourceFile, $targetFile);
    }

    if (!$src) return move_uploaded_file($sourceFile, $targetFile);

    $dst = imagecreatetruecolor($newWidth, $newHeight);
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $success = false;
    switch ($mime) {
        case 'image/jpeg': $success = @imagejpeg($dst, $targetFile, 85); break;
        case 'image/png': $success = @imagepng($dst, $targetFile); break;
        case 'image/gif': $success = @imagegif($dst, $targetFile); break;
        case 'image/webp': $success = @imagewebp($dst, $targetFile, 85); break;
    }

    imagedestroy($src);
    imagedestroy($dst);
    return $success;
}

/**
 * Skaliert ein Bild auf exakt 32×32 px und speichert es als PNG (Favicon).
 */
function resizeFavicon($sourceFile, $targetFile) {
    if (!extension_loaded('gd')) {
        return move_uploaded_file($sourceFile, $targetFile);
    }

    $info = getimagesize($sourceFile);
    if (!$info) return false;

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($sourceFile); break;
        case 'image/png':  $src = @imagecreatefrompng($sourceFile); break;
        case 'image/gif':  $src = @imagecreatefromgif($sourceFile); break;
        case 'image/webp': $src = @imagecreatefromwebp($sourceFile); break;
        default: return move_uploaded_file($sourceFile, $targetFile);
    }

    if (!$src) return move_uploaded_file($sourceFile, $targetFile);

    $dst = imagecreatetruecolor(32, 32);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $transparent);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, 32, 32, $info[0], $info[1]);
    $success = @imagepng($dst, $targetFile);
    imagedestroy($src);
    imagedestroy($dst);
    return $success;
}

$db = Database::getInstance();

// Einstellungen laden
$settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = $_POST['site_name'] ?? 'Mini-Snipe';
    $brandingType = $_POST['branding_type'] ?? 'text';
    $companyAddress = trim($_POST['company_address'] ?? '');
    $protocolHeaderText = trim($_POST['protocol_header_text'] ?? '');
    $protocolFooterText = trim($_POST['protocol_footer_text'] ?? '');
    $logoPath    = $settings['site_logo'];    // Standardwert behalten
    $faviconPath = $settings['site_favicon']; // Standardwert behalten

    // Aktuelles Logo entfernen, wenn Checkbox aktiviert ist
    if (isset($_POST['remove_logo']) && $_POST['remove_logo'] == '1') {
        if ($logoPath && file_exists(__DIR__ . '/' . $logoPath)) {
            unlink(__DIR__ . '/' . $logoPath);
        }
        $logoPath = null;
    }

    // Logo Upload
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/logo/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['site_logo']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            // Altes Logo löschen, falls vorhanden
            if ($logoPath && file_exists(__DIR__ . '/' . $logoPath)) {
                unlink(__DIR__ . '/' . $logoPath);
            }
            
            $newFilename = 'logo_' . time() . '.' . $fileExtension;
            $targetFile = $uploadDir . $newFilename;
            
            if (resizeImage($_FILES['site_logo']['tmp_name'], $targetFile, 100)) {
                $logoPath = 'uploads/logo/' . $newFilename;
            }
        } else {
            $error = "Dateityp nicht erlaubt. Erlaubt sind: " . implode(', ', $allowedExtensions);
        }
    }

    // Favicon entfernen
    if (isset($_POST['remove_favicon']) && $_POST['remove_favicon'] == '1') {
        $fixedFaviconFile = __DIR__ . '/uploads/favicon/favicon.png';
        if (file_exists($fixedFaviconFile)) {
            unlink($fixedFaviconFile);
        }
        $faviconPath = null;
    }

    // Favicon Upload
    if (!$error && isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
        $faviconUploadDir = __DIR__ . '/uploads/favicon/';
        if (!is_dir($faviconUploadDir)) {
            mkdir($faviconUploadDir, 0755, true);
        }
        $faviconExt = strtolower(pathinfo($_FILES['site_favicon']['name'], PATHINFO_EXTENSION));
        $allowedFaviconExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];
        if (in_array($faviconExt, $allowedFaviconExt)) {
            $targetFavicon = $faviconUploadDir . 'favicon.png'; // fester Name, immer überschreiben
            if (resizeFavicon($_FILES['site_favicon']['tmp_name'], $targetFavicon)) {
                $faviconPath = 'uploads/favicon/favicon.png';
            } else {
                $error = "Favicon konnte nicht verarbeitet werden.";
            }
        } else {
            $error = "Favicon: Dateityp nicht erlaubt. Erlaubt sind: JPG, PNG, GIF, WEBP, ICO.";
        }
    }

    if (!$error) {
        $stmt = $db->prepare("UPDATE settings SET site_name = ?, branding_type = ?, site_logo = ?, site_favicon = ?, company_address = ?, protocol_header_text = ?, protocol_footer_text = ? WHERE id = 1");
        if ($stmt->execute([$siteName, $brandingType, $logoPath, $faviconPath, $companyAddress, $protocolHeaderText, $protocolFooterText])) {
            $success = "Einstellungen erfolgreich gespeichert.";
            // Daten neu laden
            $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
        } else {
            $error = "Fehler beim Speichern der Einstellungen.";
        }
    }
}

// Login-Logs laden (neueste 200 Einträge)
$loginLogs = [];
try {
    $loginLogs = $db->query(
    "SELECT l.id, l.username, l.action, l.reason, l.ip_address, l.created_at,
                u.first_name, u.last_name, u.role
           FROM login_logs l
           LEFT JOIN users u ON u.id = l.user_id
          ORDER BY l.created_at DESC
          LIMIT 200"
    )->fetchAll();
} catch (\Throwable $e) {
    // Tabelle existiert noch nicht (erste Ausführung vor Migration)
    $loginLogs = [];
}

$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Globale Einstellungen - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-section {
            background: var(--bg-card);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--glass-shadow);
        }
        .form-section h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--text-main);
            border-bottom: 1px solid var(--glass-border);
            padding-bottom: 0.75rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
            display: grid;
            grid-template-columns: 200px 1fr;
            align-items: center;
            gap: 1rem;
        }
        @media (max-width: 768px) {
            .form-group { grid-template-columns: 1fr; align-items: flex-start; }
        }
        .form-group label {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            background: rgba(0,0,0,0.2);
            border: 1px solid var(--glass-border);
            color: white;
            outline: none;
            font-size: 0.9rem;
        }
        .form-control:focus {
            border-color: var(--primary-color);
        }
        textarea.form-control {
            min-height: 6.5rem;
            resize: vertical;
            line-height: 1.45;
        }
        .form-help {
            display: block;
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-top: 0.45rem;
        }
        .btn-upload {
            background: #2563eb;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .btn-upload:hover { background: #1d4ed8; }
        .logo-preview {
            margin-top: 1rem;
            max-width: 150px;
            max-height: 150px;
            border: 1px solid var(--glass-border);
            border-radius: 0.5rem;
            padding: 0.5rem;
            background: rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
    </style>
</head>
<body class="<?php echo $theme === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'settings_general'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div>
                <h1>Globale Einstellungen</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Zentrale Konfiguration der Anwendung.</p>
            </div>
            <div class="user-profile" style="display: flex; align-items: center;">
                <a href="profile.php" class="user-info" style="display: flex; align-items: center; gap: 0.5rem; margin-right: 1.5rem; background: rgba(255,255,255,0.05); padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; color: inherit;">
                    <i class="fas fa-user-circle" style="color: var(--primary-color); font-size: 1.15rem;"></i>
                    <span style="font-weight: 600; font-size: 0.875rem; color: var(--text-main);"><?php echo htmlspecialchars(Auth::getUsername()); ?></span>
                </a>
                <a href="logout.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <div style="margin-top: 2rem;">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-section">
                    <h2>Logos & Bildschirm</h2>
                    
                    <div class="form-group">
                        <label>Seitenname</label>
                        <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($settings['site_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label>Web Branding Typ</label>
                        <select name="branding_type" class="form-control">
                            <option value="text" <?php echo $settings['branding_type'] === 'text' ? 'selected' : ''; ?>>Nur Text</option>
                            <option value="logo" <?php echo $settings['branding_type'] === 'logo' ? 'selected' : ''; ?>>Nur Logo</option>
                            <option value="logo_text" <?php echo $settings['branding_type'] === 'logo_text' ? 'selected' : ''; ?>>Logo und Text</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Seiten-Logo</label>
                        <div>
                            <input type="file" name="site_logo" id="site_logo" style="display: none;" accept="image/*" onchange="previewImage(this)">
                            <button type="button" class="btn-upload" onclick="document.getElementById('site_logo').click()">
                                <i class="fas fa-file-upload"></i> Datei auswählen...
                            </button>
                            <small style="display: block; color: var(--text-muted); margin-top: 0.5rem;">Erlaubte Formate: JPG, PNG, GIF, SVG, WEBP.</small>
                            
                            <?php if ($settings['site_logo']): ?>
                                <div class="logo-preview">
                                    <img src="<?php echo htmlspecialchars($settings['site_logo']); ?>" alt="Vorschau" id="img_preview">
                                </div>
                                <label style="display: flex; gap: 0.5rem; align-items: center; margin-top: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="remove_logo" value="1"> Aktuelles Seiten-Logo Bild entfernen
                                </label>
                            <?php else: ?>
                                <div class="logo-preview" style="display: none;" id="preview_container">
                                    <img src="" alt="Vorschau" id="img_preview">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                    <div class="form-group">
                        <label>Favicon</label>
                        <div>
                            <input type="file" name="site_favicon" id="site_favicon" style="display:none;" accept="image/*" onchange="previewFavicon(this)">
                            <button type="button" class="btn-upload" onclick="document.getElementById('site_favicon').click()">
                                <i class="fas fa-file-upload"></i> Favicon auswählen...
                            </button>
                            <small style="display:block; color:var(--text-muted); margin-top:0.5rem;">Wird serverseitig auf 32&times;32 px skaliert. Erlaubt: JPG, PNG, GIF, WEBP, ICO.</small>

                            <?php $faviconExists = file_exists(__DIR__ . '/uploads/favicon/favicon.png'); ?>
                            <?php if ($faviconExists): ?>
                                <div style="display:flex; align-items:center; gap:1rem; margin-top:0.75rem;">
                                    <div style="width:48px; height:48px; border:1px solid var(--glass-border); border-radius:0.375rem; background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                        <img src="uploads/favicon/favicon.png?v=<?php echo filemtime(__DIR__ . '/uploads/favicon/favicon.png'); ?>" alt="Favicon" style="width:32px; height:32px;" id="favicon_preview">
                                    </div>
                                    <label style="display:flex; gap:0.5rem; align-items:center; cursor:pointer;">
                                        <input type="checkbox" name="remove_favicon" value="1"> Aktuelles Favicon entfernen
                                    </label>
                                </div>
                            <?php else: ?>
                                <div style="display:none; margin-top:0.75rem;" id="favicon_preview_container">
                                    <div style="width:48px; height:48px; border:1px solid var(--glass-border); border-radius:0.375rem; background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                        <img src="" alt="Favicon" style="width:32px; height:32px; object-fit:contain;" id="favicon_preview">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                        <label>Firmenadresse</label>
                        <div>
                            <textarea name="company_address" class="form-control" rows="3"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                            <small class="form-help">Mehrzeilig. Dieser Text erscheint oben im Ausdruck.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Header-Text</label>
                        <div>
                            <textarea name="protocol_header_text" class="form-control" rows="6"><?php echo htmlspecialchars($settings['protocol_header_text'] ?? ''); ?></textarea>
                            <small class="form-help">Einleitungstext oberhalb der Asset-Tabelle fuer Ausgabe- und Rueckgabeprotokolle.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Footer-Text</label>
                        <div>
                            <textarea name="protocol_footer_text" class="form-control" rows="2"><?php echo htmlspecialchars($settings['protocol_footer_text'] ?? ''); ?></textarea>
                            <small class="form-help">Kurzer Fusszeilentext fuer Ausdrucke und Ablage.</small>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right:0.5rem;"></i> Einstellungen speichern</button>
                </div>
            </form>

            <!-- ===== Login-Protokoll ===== -->
            <div class="form-section" style="margin-top: 2.5rem;">
                <h2><i class="fas fa-list-alt" style="margin-right:0.5rem; color:var(--primary-color);"></i> Login-Protokoll</h2>
                <p style="color:var(--text-muted); font-size:0.875rem; margin-bottom:1.25rem;">Die letzten 200 Login-Events inkl. Fehlversuche und gesperrte Anmeldungen.</p>

                <?php if (empty($loginLogs)): ?>
                    <p style="color:var(--text-muted); font-size:0.875rem;">Noch keine Einträge vorhanden.</p>
                <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="data-table" style="font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th>Datum / Uhrzeit</th>
                            <th>Aktion</th>
                            <th>Benutzername</th>
                            <th>Name</th>
                            <th>Rolle</th>
                            <th>Grund</th>
                            <th>IP-Adresse</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($loginLogs as $log): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?php echo htmlspecialchars($log['created_at']); ?></td>
                            <td>
                                <?php if ($log['action'] === 'login'): ?>
                                    <span class="badge badge-success"><i class="fas fa-sign-in-alt"></i> Login</span>
                                <?php elseif ($log['action'] === 'login_failed'): ?>
                                    <span class="badge" style="background: rgba(239,68,68,0.18); color: #ef4444; border: 1px solid rgba(239,68,68,0.35);"><i class="fas fa-ban"></i> Login fehlgeschlagen</span>
                                <?php elseif ($log['action'] === 'login_blocked'): ?>
                                    <span class="badge" style="background: rgba(245,158,11,0.18); color: #f59e0b; border: 1px solid rgba(245,158,11,0.35);"><i class="fas fa-lock"></i> Login gesperrt</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><i class="fas fa-sign-out-alt"></i> Logout</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo htmlspecialchars(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars($log['role'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($log['reason'] ?? ''); ?></td>
                            <td style="font-family:monospace; font-size:0.8rem;"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('img_preview');
            const container = document.getElementById('preview_container') || preview.parentElement;
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    container.style.display = 'flex';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewFavicon(input) {
            const preview = document.getElementById('favicon_preview');
            const container = document.getElementById('favicon_preview_container');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    if (container) container.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
