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

$db = Database::getInstance();

// Einstellungen laden
$settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siteName = $_POST['site_name'] ?? 'Mini-Snipe';
    $brandingType = $_POST['branding_type'] ?? 'text';
    $logoPath = $settings['site_logo']; // Standardwert behalten

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

    if (!$error) {
        $stmt = $db->prepare("UPDATE settings SET site_name = ?, branding_type = ?, site_logo = ? WHERE id = 1");
        if ($stmt->execute([$siteName, $brandingType, $logoPath])) {
            $success = "Einstellungen erfolgreich gespeichert.";
            // Daten neu laden
            $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
        } else {
            $error = "Fehler beim Speichern der Einstellungen.";
        }
    }
}

$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
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
    <div class="sidebar">
        <div class="logo">Mini-Snipe</div>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="assets.php" class="nav-link"><i class="fas fa-laptop"></i> Assets</a>
            <a href="users.php" class="nav-link"><i class="fas fa-users"></i> User</a>
            <?php if (Auth::isAdmin()): ?>
                <a href="locations.php" class="nav-link"><i class="fas fa-map-marker-alt"></i> Standorte</a>
                <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Verwaltung</a>
                <a href="settings_general.php" class="nav-link active"><i class="fas fa-sliders-h"></i> Einstellungen</a>
            <?php endif; ?>
        </nav>
    </div>

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

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right:0.5rem;"></i> Einstellungen speichern</button>
                </div>
            </form>
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
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
