<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\UserController;
use App\Helpers\Auth;

Auth::requireLogin();

$db = Database::getInstance();
$userController = new UserController($db);

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    header('Location: login.php');
    exit;
}

$user = $userController->getUserById($userId);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = "Bitte alle Felder ausfüllen.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Die neuen Passwörter stimmen nicht überein.";
        } elseif (strlen($newPassword) < 6) {
            $error = "Das neue Passwort muss mindestens 6 Zeichen lang sein.";
        } else {
            if ($userController->changePassword($userId, $oldPassword, $newPassword)) {
                $success = "Passwort erfolgreich geändert.";
            } else {
                $error = "Das alte Passwort ist inkorrekt.";
            }
        }
    }
}

$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil & Einstellungen - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 1rem;
        }
        @media (max-width: 768px) {
            .profile-container { grid-template-columns: 1fr; }
        }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .form-control:focus { border-color: var(--primary-color); }
        
        /* Switch Toggle Styles */
        .switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
        }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255,255,255,0.1);
            transition: .4s; border: 1px solid var(--glass-border);
        }
        .slider:before {
            position: absolute; content: "";
            height: 18px; width: 18px;
            left: 3px; bottom: 2px;
            background-color: white; transition: .4s;
        }
        input:checked + .slider { background-color: var(--primary-color); }
        input:focus + .slider { box-shadow: 0 0 1px var(--primary-color); }
        input:checked + .slider:before { transform: translateX(22px); }
        .slider.round { border-radius: 24px; }
        .slider.round:before { border-radius: 50%; }
    </style>
</head>
<body class="<?php echo $theme === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = ''; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <div>
                <h1>Profil & Einstellungen</h1>
                <p style="color: var(--text-muted); margin-top: 0.25rem;">Verwalte dein Konto und die Anzeige.</p>
            </div>
            <div class="user-profile" style="display: flex; align-items: center;">
                <div class="user-info" style="display: flex; align-items: center; gap: 0.5rem; margin-right: 1.5rem; background: rgba(255,255,255,0.05); padding: 0.5rem 1rem; border-radius: 0.5rem;">
                    <i class="fas fa-user-circle" style="color: var(--primary-color); font-size: 1.15rem;"></i>
                    <span style="font-weight: 600; font-size: 0.875rem; color: var(--text-main);"><?php echo htmlspecialchars(Auth::getUsername()); ?></span>
                </div>
                <a href="logout.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Passwort ändern -->
            <div class="card">
                <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-lock" style="margin-right: 0.5rem; color: var(--primary-color);"></i> Passwort ändern</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label>Altes Passwort</label>
                        <input type="password" name="old_password" class="form-control" required placeholder="Dein aktuelles Passwort">
                    </div>
                    
                    <div class="form-group">
                        <label>Neues Passwort</label>
                        <input type="password" name="new_password" class="form-control" required placeholder="Mindestens 6 Zeichen">
                    </div>
                    
                    <div class="form-group">
                        <label>Passwort bestätigen</label>
                        <input type="password" name="confirm_password" class="form-control" required placeholder="Neues Passwort wiederholen">
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-save" style="margin-right: 0.5rem;"></i> Speichern</button>
                </form>
            </div>

            <!-- Darstellung -->
            <div class="card">
                <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-paint-brush" style="margin-right: 0.5rem; color: #a855f7;"></i> Darstellung</h3>
                <div>
                    <div class="form-group">
                        <label>Farbschema umschalten</label>
                        <div style="display: flex; gap: 1rem; align-items: center; background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 0.5rem; border: 1px solid var(--glass-border);">
                            <label class="switch">
                                <input type="checkbox" id="themeToggle" <?php echo $theme === 'light' ? 'checked' : ''; ?> onchange="toggleTheme()">
                                <span class="slider round"></span>
                            </label>
                            <span id="themeLabel" style="font-weight: 500;"><?php echo $theme === 'light' ? 'Helles Design' : 'Dunkles Design'; ?></span>
                        </div>
                    </div>
                    <p style="color: var(--text-muted); font-size: 0.825rem;">Das Farbschema wird als Cookie gespeichert und auf allen Seiten angewendet.</p>
                </div>
            </div>
        </div>

    </main>

    <script>
        function toggleTheme() {
            const toggle = document.getElementById('themeToggle');
            const isLight = toggle.checked;
            
            if (isLight) {
                document.body.classList.add('light-mode');
                document.getElementById('themeLabel').textContent = 'Helles Design';
            } else {
                document.body.classList.remove('light-mode');
                document.getElementById('themeLabel').textContent = 'Dunkles Design';
            }
            
            // Cookie setzen (1 Jahr gültig)
            document.cookie = "theme=" + (isLight ? "light" : "dark") + "; path=/; max-age=31536000";
        }
    </script>
</body>
</html>
