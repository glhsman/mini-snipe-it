<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';
require_once __DIR__ . '/../src/Helpers/Mail.php';

use App\Controllers\UserController;
use App\Helpers\Auth;
use App\Helpers\Mail;

if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

Auth::startSession();

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function buildBaseUrl(): string {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $scriptDir = rtrim($scriptDir, '/');
    return $scheme . '://' . $host . ($scriptDir === '' ? '' : $scriptDir);
}

$db = Database::getInstance();
$userController = new UserController($db);
$settings = $db->query("SELECT site_name, mail_test_success_at FROM settings WHERE id = 1")->fetch() ?: [];
$passwordResetEnabled = !empty($settings['mail_test_success_at']);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $postedCsrf)) {
        $error = 'Ungültige Anfrage. Bitte Seite neu laden.';
    } elseif (!$passwordResetEnabled) {
        $error = 'Die Passwort-Zurücksetzung ist derzeit nicht verfügbar.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $genericSuccess = 'Wenn zu diesem Benutzernamen ein aktiver Account mit hinterlegter E-Mail-Adresse existiert, wurde ein Reset-Link versendet.';

        if ($username === '') {
            $error = 'Bitte einen Benutzernamen eingeben.';
        } else {
            $user = $userController->getPasswordResetUserByUsername($username);
            if ($user && !empty($user['email']) && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', time() + 1800);
                $resetUrl = buildBaseUrl() . '/reset_password.php?token=' . urlencode($token);
                $siteName = trim((string) ($settings['site_name'] ?? 'Mini-Snipe'));
                $mailResult = Mail::sendTextMail(
                    (string) $user['email'],
                    $siteName . ' Passwort zurücksetzen',
                    "Hallo " . (string) $user['username'] . ",\n\n"
                    . "für Ihren Account wurde eine Passwort-Zurücksetzung angefordert.\n\n"
                    . "Bitte öffnen Sie innerhalb von 30 Minuten folgenden Link:\n"
                    . $resetUrl . "\n\n"
                    . "Falls Sie diese Anfrage nicht ausgelöst haben, können Sie diese E-Mail ignorieren.\n\n"
                    . "Viele Grüße\n" . $siteName
                );

                if ($mailResult['success']) {
                    $userController->createPasswordReset((int) $user['id'], $tokenHash, $expiresAt, $_SERVER['REMOTE_ADDR'] ?? null);
                }
            }

            $success = $genericSuccess;
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
    <title>Passwort vergessen - Mini-Snipe</title>
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
        <h1 style="margin-bottom: 0.5rem;">Passwort vergessen</h1>
        <p style="color: var(--text-muted); margin-bottom: 1.75rem;">Geben Sie Ihren Benutzernamen ein. Der Reset-Link wird ausschließlich an die im Benutzerkonto hinterlegte E-Mail-Adresse gesendet.</p>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($passwordResetEnabled): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="form-group">
                    <label>Benutzername</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Reset-Link senden</button>
            </form>
        <?php else: ?>
            <div class="alert alert-error">Die Passwort-Zurücksetzung ist derzeit deaktiviert, weil noch kein erfolgreicher Mail-Test hinterlegt ist.</div>
        <?php endif; ?>

        <div style="margin-top: 1rem; text-align: center;">
            <a href="login.php" style="color: var(--text-muted); text-decoration: none;">Zurück zum Login</a>
        </div>
    </div>
</body>
</html>
