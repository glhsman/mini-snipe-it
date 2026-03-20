<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\UserController;
use App\Helpers\Auth;

if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

Auth::startSession();

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = Database::getInstance();
$userController = new UserController($db);
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$resetEntry = $token !== '' ? $userController->getPasswordResetByToken($token) : false;

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $postedCsrf)) {
        $error = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif (!$resetEntry) {
        $error = 'Der Link ist ungültig oder abgelaufen.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $error = 'Das neue Passwort muss mindestens 8 Zeichen lang sein.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Die eingegebenen Passwörter stimmen nicht überein.';
        } else {
            $db->beginTransaction();
            try {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $userController->updatePasswordHash((int) $resetEntry['user_id'], $passwordHash);
                $userController->markPasswordResetUsed((int) $resetEntry['id']);
                $db->commit();
                $success = 'Ihr Passwort wurde erfolgreich gesetzt. Sie können sich jetzt anmelden.';
                $resetEntry = false;
            } catch (\Throwable $e) {
                $db->rollBack();
                $error = 'Das Passwort konnte nicht gespeichert werden.';
            }
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
    <title>Passwort zurücksetzen - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <style>
        body { justify-content: center; align-items: center; }
        .reset-card { width: min(520px, 94vw); }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.25rem; font-size: 0.875rem; }
        .alert-success { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.2); }
        .alert-error { background: rgba(244,63,94,0.1); color: var(--accent-rose); border: 1px solid rgba(244,63,94,0.2); }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <div class="card reset-card">
        <h1 style="margin-bottom: 0.5rem;">Passwort zurücksetzen</h1>
        <p style="color: var(--text-muted); margin-bottom: 1.75rem;">Vergeben Sie ein neues Passwort für Ihren Account.</p>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($resetEntry): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label>Neues Passwort</label>
                    <input type="password" name="password" class="form-control" required minlength="8" autofocus>
                </div>
                <div class="form-group">
                    <label>Passwort bestätigen</label>
                    <input type="password" name="password_confirm" class="form-control" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Passwort speichern</button>
            </form>
        <?php elseif (!$success): ?>
            <div class="alert alert-error">Der Link ist ungültig oder abgelaufen.</div>
        <?php endif; ?>

        <div style="margin-top: 1rem; text-align: center;">
            <a href="login.php" style="color: var(--text-muted); text-decoration: none;">Zurück zum Login</a>
        </div>
    </div>
</body>
</html>
