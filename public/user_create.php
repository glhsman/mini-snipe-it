<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\UserController;
use App\Controllers\MasterDataController;
use App\Helpers\Auth;

// Nur Admins dürfen neue Setup/User anlegen
Auth::requireAdmin();

$db = Database::getInstance();
$userController = new UserController($db);
$masterData = new MasterDataController($db);

$locations = $masterData->getLocations();
$error = null;
$success = null;

$canLogin = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $canLogin = isset($_POST['can_login']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    
    $data = [
        'first_name'  => $_POST['first_name'] ?? '',
        'last_name'   => $_POST['last_name'] ?? '',
        'email'       => $_POST['email'] ?? '',
        'personalnummer' => $_POST['personalnummer'] ?? '',
        'vorgesetzter' => $_POST['vorgesetzter'] ?? '',
        'is_activ'    => isset($_POST['is_activ']) ? 1 : 0,
        'username'    => $_POST['username'] ?? '',
        'location_id' => !empty($_POST['location_id']) ? (int)$_POST['location_id'] : null,
        'password'    => $password,
        'can_login'   => $canLogin,
        'role'        => $_POST['role'] ?? 'user'
    ];

    if (empty($data['username'])) {
        $error = "Benutzername ist ein Pflichtfeld.";
    } elseif ($canLogin === 1 && empty($data['password'])) {
        $error = "Wenn Web-Login erlaubt ist, ist ein Passwort erforderlich.";
    } elseif ($canLogin === 1 && $password !== $password_confirm) {
        $error = "Die eingegebenen Passwörter stimmen nicht überein.";
    } else {
        if ($canLogin === 0) {
            $data['password'] = '';
        }
        try {
            if ($userController->createUser($data)) {
                header('Location: users.php');
                exit;
            } else {
                $error = "Fehler beim Anlegen des Benutzers (möglicherweise existiert der Benutzername/E-Mail bereits).";
            }
        } catch (\PDOException $e) {
            // Fängt Unique-Constraint-Fehler etc. ab
            $error = "Datenbankfehler: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzer anlegen - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; align-items: end; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .form-control:focus { border-color: var(--primary-color); }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .alert-error { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }
        .password-wrapper { position: relative; }
        .password-toggle { position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-muted); z-index: 10; padding: 0.5rem; }
        .password-toggle:hover { color: white; }
        input[type="password"]::-ms-reveal, input[type="password"]::-ms-clear { display: none; }
        .form-control option, .form-control optgroup { background: #1f2937; color: white; }
        .light-mode .form-control option, .light-mode .form-control optgroup { background: #ffffff; color: #1e293b; }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'users'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1>Neuen Benutzer anlegen</h1>
            <a href="users.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-arrow-left"></i> Zurück</a>
        </header>

        <div class="card" style="max-width: 800px;">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <input type="text" name="fake_username" autocomplete="username" style="display:none;">
                <input type="password" name="fake_password" autocomplete="current-password" style="display:none;">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: flex; gap: 0.6rem; align-items: center; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" name="can_login" id="can_login" value="1" <?php echo $canLogin ? 'checked' : ''; ?>>
                        Web-Login erlauben
                    </label>
                    <small style="color: var(--text-muted);">Wenn deaktiviert, kann sich der Benutzer nicht anmelden und es ist kein Passwort erforderlich.</small>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Vorname</label>
                        <input type="text" name="first_name" class="form-control" autocomplete="off" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Nachname</label>
                        <input type="text" name="last_name" class="form-control" autocomplete="off" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Benutzername (Pflichtfeld)</label>
                        <input type="text" name="username" class="form-control" autocomplete="off" autocapitalize="off" spellcheck="false" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>E-Mail</label>
                        <input type="email" name="email" class="form-control" autocomplete="off" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Personalnummer</label>
                        <input type="text" name="personalnummer" maxlength="10" class="form-control" autocomplete="off" value="<?php echo htmlspecialchars($_POST['personalnummer'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Vorgesetzter</label>
                        <input type="text" name="vorgesetzter" maxlength="100" class="form-control" autocomplete="off" value="<?php echo htmlspecialchars($_POST['vorgesetzter'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: flex; gap: 0.6rem; align-items: center; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" name="is_activ" value="1" <?php echo !isset($_POST['is_activ']) || $_POST['is_activ'] ? 'checked' : ''; ?>>
                        Benutzer ist aktiv
                    </label>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label id="passwordLabel">Passwort (Pflichtfeld bei Web-Login)</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="pwd1" class="form-control" autocomplete="new-password">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('pwd1', this)"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label id="passwordConfirmLabel">Passwort bestätigen</label>
                        <div class="password-wrapper">
                            <input type="password" name="password_confirm" id="pwd2" class="form-control" autocomplete="new-password">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('pwd2', this)"></i>
                        </div>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Standort</label>
                        <select name="location_id" class="form-control">
                            <option value="">- Kein Standort -</option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo $loc['id']; ?>" <?php echo ((isset($_POST['location_id']) && $_POST['location_id'] == $loc['id']) ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($loc['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Rolle</label>
                        <select name="role" class="form-control">
                            <option value="user" <?php echo (($_POST['role'] ?? 'user') === 'user') ? 'selected' : ''; ?>>Benutzer</option>
                            <option value="editor" <?php echo (($_POST['role'] ?? 'user') === 'editor') ? 'selected' : ''; ?>>Bearbeiter</option>
                            <option value="admin" <?php echo (($_POST['role'] ?? 'user') === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Benutzer speichern</button>
                    <a href="users.php" class="btn" style="background: rgba(255,255,255,0.1); margin-left: 10px;">Abbrechen</a>
                </div>
            </form>
        </div>
    </main>
    <script>
        function updatePasswordRequirement() {
            const canLogin = document.getElementById('can_login').checked;
            const pwd1 = document.getElementById('pwd1');
            const pwd2 = document.getElementById('pwd2');
            const passwordLabel = document.getElementById('passwordLabel');

            pwd1.required = canLogin;
            pwd2.required = canLogin;
            pwd1.disabled = !canLogin;
            pwd2.disabled = !canLogin;

            if (!canLogin) {
                pwd1.value = '';
                pwd2.value = '';
                passwordLabel.textContent = 'Passwort (nicht erforderlich)';
            } else {
                passwordLabel.textContent = 'Passwort (Pflichtfeld bei Web-Login)';
            }
        }

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        document.getElementById('can_login').addEventListener('change', updatePasswordRequirement);
        updatePasswordRequirement();
    </script>
</body>
</html>
