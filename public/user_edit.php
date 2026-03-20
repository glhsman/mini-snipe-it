<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Controllers\UserController;
use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireEditor();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$userId = (int)$_GET['id'];
$db = Database::getInstance();
$userController = new UserController($db);
$assetController = new AssetController($db);
$masterData = new MasterDataController($db);

$user = $userController->getUserById($userId);
if (!$user) {
    header('Location: users.php');
    exit;
}

$locations = $masterData->getLocations();
$assignedAssets = $assetController->getAssetsByUserId($userId);
$assignedAssetCount = count($assignedAssets);
$error = null;
$success = null;

$canLogin = isset($user['can_login']) ? (int)$user['can_login'] : 1;

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
        'can_login'   => $canLogin
    ];

    if (Auth::isAdmin()) {
        $data['role'] = $_POST['role'] ?? 'user';
    }

    if (empty($data['username'])) {
        $error = "Benutzername darf nicht leer sein.";
    } elseif ($canLogin === 1 && $password !== $password_confirm) {
        $error = "Die eingegebenen Passwörter stimmen nicht überein.";
    } elseif (
        $canLogin === 1 &&
        empty($password) &&
        ((int)($user['can_login'] ?? 1) === 0 || empty($user['password']))
    ) {
        $error = "Wenn Web-Login aktiviert wird, ist ein Passwort erforderlich.";
    } else {
        if ($canLogin === 0) {
            $data['password'] = '';
        }
        if ($userController->updateUser($userId, $data)) {
            header('Location: users.php');
            exit;
        } else {
            $error = "Fehler beim Speichern der Benutzerdaten. Möglicherweise existiert der Benutzername oder die E-Mail bereits.";
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
    <title>Benutzer bearbeiten - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
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
        .protocol-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
        .protocol-hint { color: var(--text-muted); font-size: 0.825rem; margin-bottom: 1.5rem; }
        .asset-list { margin: 1.5rem 0 2rem; border: 1px solid var(--glass-border); border-radius: 0.85rem; overflow: hidden; }
        .asset-list table { width: 100%; border-collapse: collapse; }
        .asset-list th, .asset-list td { padding: 0.85rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.06); text-align: left; }
        .asset-list th { color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; background: rgba(255,255,255,0.02); }
        .asset-list tr:last-child td { border-bottom: 0; }
        .asset-inline-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-small { padding: 0.5rem 0.75rem; font-size: 0.82rem; }
        .asset-list-controls { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-top: 1px solid rgba(255,255,255,0.06); background: rgba(255,255,255,0.01); }
        .checkbox-col { width: 44px; text-align: center; }
        .checkbox-col input { width: 16px; height: 16px; }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'users'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1>Benutzer bearbeiten</h1>
            <a href="users.php" class="btn" style="background: rgba(255,255,255,0.1);"><i class="fas fa-arrow-left"></i> Zurück</a>
        </header>

        <div class="card" style="max-width: 800px;">
            <div class="protocol-actions">
                <a href="user_protocol.php?id=<?php echo $userId; ?>&type=handover" class="btn" target="_blank" rel="noopener" style="background: rgba(34, 197, 94, 0.16); color: #86efac; border: 1px solid rgba(34, 197, 94, 0.28);">
                    <i class="fas fa-file-export"></i> Ausgabeprotokoll erzeugen
                </a>
            </div>
            <div class="protocol-hint">
                Aktuell zugewiesene Assets: <?php echo $assignedAssetCount; ?>. Das Ausgabeprotokoll enthaelt immer den aktuellen Gesamtbestand.
            </div>

            <?php if ($assignedAssetCount > 0): ?>
                <div class="asset-list">
                    <form method="POST" action="asset_checkin.php" id="bulk-return-form" onsubmit="return validateBulkReturn();">
                        <input type="hidden" name="user_id" value="<?php echo $userId; ?>">
                        <table>
                        <thead>
                            <tr>
                                <th class="checkbox-col"><input type="checkbox" id="toggle-all-assets" title="Alle auswaehlen"></th>
                                <th>Asset</th>
                                <th>Seriennummer</th>
                                <th>Inventar-Nr</th>
                                <th>Ausgegeben am</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignedAssets as $assignedAsset): ?>
                                <tr>
                                    <td class="checkbox-col"><input type="checkbox" name="asset_ids[]" value="<?php echo $assignedAsset['id']; ?>" class="asset-checkbox"></td>
                                    <td><?php echo htmlspecialchars($assignedAsset['name'] ?: $assignedAsset['model_name'] ?: 'Unbekannt'); ?></td>
                                    <td><?php echo htmlspecialchars($assignedAsset['serial'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($assignedAsset['asset_tag'] ?? '-'); ?></td>
                                    <td>
                                        <?php if (!empty($assignedAsset['assigned_at'])): ?>
                                            <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($assignedAsset['assigned_at']))); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        </table>
                        <div class="asset-list-controls">
                            <small style="color: var(--text-muted);">Mehrere Assets markieren und gemeinsam ruecknehmen.</small>
                            <button type="submit" class="btn btn-small" style="background: rgba(249, 115, 22, 0.16); color: #fdba74; border: 1px solid rgba(249, 115, 22, 0.28);">
                                <i class="fas fa-undo"></i> Auswahl rueckgeben + Protokoll
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
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
                        <input type="text" name="first_name" class="form-control" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Nachname</label>
                        <input type="text" name="last_name" class="form-control" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Benutzername (Pflichtfeld)</label>
                        <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>E-Mail</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Personalnummer</label>
                        <input type="text" name="personalnummer" maxlength="10" class="form-control" value="<?php echo htmlspecialchars($user['personalnummer'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Vorgesetzter</label>
                        <input type="text" name="vorgesetzter" maxlength="100" class="form-control" value="<?php echo htmlspecialchars($user['vorgesetzter'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: flex; gap: 0.6rem; align-items: center; cursor: pointer; color: var(--text-main);">
                        <input type="checkbox" name="is_activ" value="1" <?php echo ((int)($user['is_activ'] ?? 1) === 1) ? 'checked' : ''; ?>>
                        Benutzer ist aktiv
                    </label>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label id="passwordLabel">Passwort (Pflichtfeld bei Web-Login)</label>
                        <div class="password-wrapper">
                            <input type="password" name="password" id="pwd1" class="form-control" placeholder="••••••••">
                            <i class="fas fa-eye password-toggle" onclick="togglePassword('pwd1', this)"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Passwort bestätigen</label>
                        <div class="password-wrapper">
                            <input type="password" name="password_confirm" id="pwd2" class="form-control" placeholder="••••••••">
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
                                <option value="<?php echo $loc['id']; ?>" <?php echo ($user['location_id'] == $loc['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (Auth::isAdmin()): ?>
                    <div class="form-group">
                        <label>Rolle</label>
                        <select name="role" class="form-control">
                            <option value="user" <?php echo ($user['role'] == 'user') ? 'selected' : ''; ?>>Benutzer</option>
                            <option value="editor" <?php echo ($user['role'] == 'editor') ? 'selected' : ''; ?>>Bearbeiter</option>
                            <option value="admin" <?php echo ($user['role'] == 'admin') ? 'selected' : ''; ?>>Administrator</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Speichern</button>
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

        function validateBulkReturn() {
            const checked = document.querySelectorAll('.asset-checkbox:checked').length;
            if (checked === 0) {
                alert('Bitte waehle mindestens ein Asset fuer die Rueckgabe aus.');
                return false;
            }
            return confirm('Ausgewaehlte Assets jetzt rueckgeben und Rueckgabeprotokoll erzeugen?');
        }

        const toggleAll = document.getElementById('toggle-all-assets');
        if (toggleAll) {
            toggleAll.addEventListener('change', function () {
                document.querySelectorAll('.asset-checkbox').forEach(cb => cb.checked = this.checked);
            });
        }

        document.getElementById('can_login').addEventListener('change', updatePasswordRequirement);
        updatePasswordRequirement();
    </script>
</body>
</html>
