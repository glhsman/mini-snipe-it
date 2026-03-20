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

$error = null;
$db = Database::getInstance();
$settings = $db->query("SELECT mail_test_success_at FROM settings WHERE id = 1")->fetch() ?: [];
$passwordResetEnabled = !empty($settings['mail_test_success_at']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userController = new UserController($db);

    $usernameInput = trim((string) ($_POST['username'] ?? ''));
    $passwordInput = (string) ($_POST['password'] ?? '');

    $authResult = $userController->authenticateDetailed($usernameInput, $passwordInput);

    if ($authResult['success']) {
        Auth::login($authResult['user']);
        header('Location: index.php');
        exit;
    } else {
        if ($authResult['reason'] === 'login_disabled') {
            Auth::logLoginBlocked($authResult['username'] ?: $usernameInput, 'login_disabled', $authResult['user_id']);
        } else {
            Auth::logLoginFailed($authResult['username'] ?: $usernameInput, (string) $authResult['reason'], $authResult['user_id']);
        }
        $error = "Ungültiger Benutzername oder Passwort.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { justify-content: center; align-items: center; background: radial-gradient(circle at top right, #1e293b, var(--bg-dark)); }
        .login-layout {
            width: min(980px, 96vw);
            display: grid;
            grid-template-columns: minmax(320px, 420px) minmax(320px, 1fr);
            gap: 1rem;
            align-items: stretch;
        }
        .login-card { width: 100%; }
        .request-entry-card {
            border-radius: 1rem;
            border: 1px solid rgba(99, 102, 241, 0.45);
            background:
                radial-gradient(circle at 15% 12%, rgba(99, 102, 241, 0.16), transparent 36%),
                radial-gradient(circle at 85% 92%, rgba(14, 165, 233, 0.14), transparent 32%),
                rgba(2, 6, 23, 0.86);
            box-shadow: 0 20px 45px rgba(2, 6, 23, 0.35), inset 0 0 0 1px rgba(148, 163, 184, 0.08);
            padding: 1.4rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            min-height: 380px;
            position: relative;
            overflow: hidden;
        }
        .request-entry-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(170deg, rgba(99, 102, 241, 0.08), transparent 55%);
            pointer-events: none;
        }
        .request-content {
            position: relative;
            z-index: 1;
        }
        .request-kicker {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #bfdbfe;
            margin-bottom: 0.8rem;
        }
        .request-entry-card h2 {
            margin: 0 0 0.6rem;
            font-size: 1.35rem;
            color: #e2e8f0;
        }
        .request-entry-card p {
            margin: 0;
            color: #94a3b8;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        body.light-mode .request-entry-card h2 {
            color: #f8fafc;
        }
        body.light-mode .request-entry-card p {
            color: #cbd5e1;
        }
        .request-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.2rem;
            width: 100%;
            padding: 0.9rem 1rem;
            border-radius: 0.85rem;
            text-decoration: none;
            font-weight: 700;
            color: #e2e8f0;
            background: linear-gradient(90deg, #0284c7, #2563eb);
            border: 1px solid rgba(125, 211, 252, 0.55);
            box-shadow: 0 8px 24px rgba(2, 132, 199, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .request-cta:hover {
            transform: translateY(-1px);
            box-shadow: 0 14px 30px rgba(37, 99, 235, 0.38);
        }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); font-size: 0.875rem; }
        @media (max-width: 860px) {
            .login-layout {
                grid-template-columns: 1fr;
            }
            .request-entry-card {
                min-height: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="login-layout">
        <div class="card login-card">
            <h1 style="margin-bottom: 0.5rem;">Login</h1>
            <p style="color: var(--text-muted); margin-bottom: 2rem;">Bitte melde dich an, um fortzufahren.</p>

            <?php if (isset($_GET['timeout']) && $_GET['timeout'] === '1'): ?>
                <div class="alert" style="background: rgba(234,179,8,0.12); color: #fbbf24; border-color: rgba(234,179,8,0.3);">
                    <i class="fas fa-clock" style="margin-right:0.5rem;"></i>
                    Deine Sitzung wurde nach 2 Stunden Inaktivität automatisch beendet. Bitte melde dich erneut an.
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label>Benutzername</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label>Passwort</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <?php if ($passwordResetEnabled): ?>
                    <div style="margin-top: -0.75rem; margin-bottom: 1.25rem; text-align: right;">
                        <a href="forgot_password.php" style="color: var(--primary-color); text-decoration: none; font-size: 0.875rem;">Passwort vergessen?</a>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Anmelden</button>
            </form>
        </div>

        <div class="request-entry-card">
            <div class="request-content">
                <div class="request-kicker"><i class="fas fa-paper-plane"></i> Oeffentliche Anforderung</div>
                <h2>Neues Geraet benoetigt?</h2>
                <p>Wenn Sie keinen Zugang zum Asset-System haben, koennen Sie hier direkt eine neue Anforderung einreichen.</p>
                <a href="asset_request_public.php" class="request-cta">
                    <i class="fas fa-clipboard-check"></i>
                    Nur neues Geraet anfordern
                </a>
            </div>
        </div>
    </div>
</body>
</html>
