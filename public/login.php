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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $userController = new UserController($db);
    
    $user = $userController->authenticate($_POST['username'], $_POST['password']);
    if ($user) {
        Auth::login($user);
        header('Location: index.php');
        exit;
    } else {
        $error = "Ungültiger Benutzername oder Passwort.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body { justify-content: center; align-items: center; background: radial-gradient(circle at top right, #1e293b, var(--bg-dark)); }
        .login-card { max-width: 400px; width: 100%; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="card login-card">
        <h1 style="margin-bottom: 0.5rem;">Login</h1>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Bitte melde dich an, um fortzufahren.</p>

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
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Anmelden</button>
        </form>
    </div>
</body>
</html>
