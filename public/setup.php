<?php
require_once __DIR__ . '/../src/Controllers/SetupController.php';

use App\Controllers\SetupController;

$setup = new SetupController();
$error = null;
$success = null;

if ($setup->isInstalled()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'db_host' => $_POST['db_host'] ?? '',
        'db_name' => $_POST['db_name'] ?? '',
        'db_user' => $_POST['db_user'] ?? '',
        'db_pass' => $_POST['db_pass'] ?? ''
    ];

    $test = $setup->testConnection($data);
    if ($test === true) {
        $migration = $setup->runMigrations($data);
        if ($migration === true) {
            if ($setup->saveConfig($data)) {
                $success = "Installation erfolgreich! Alle Tabellen und Demo-Daten wurden angelegt.<br><br>"
                    . "<strong>Demo-Zugangsdaten (Passwort überall: <code>password</code>)</strong><br>"
                    . "<table style='margin-top:0.5rem; border-collapse:collapse; font-size:0.85rem;'>"
                    . "<tr><th style='text-align:left; padding:0.2rem 1rem 0.2rem 0; color:inherit;'>Benutzername</th><th style='text-align:left; padding:0.2rem 1rem 0.2rem 0; color:inherit;'>Rolle</th></tr>"
                    . "<tr><td style='padding:0.15rem 1rem 0.15rem 0;'><strong>admin</strong></td><td>Administrator</td></tr>"
                    . "<tr><td style='padding:0.15rem 1rem 0.15rem 0;'>mmuster</td><td>Editor</td></tr>"
                    . "<tr><td style='padding:0.15rem 1rem 0.15rem 0;'>aschmidt</td><td>Benutzer</td></tr>"
                    . "<tr><td style='padding:0.15rem 1rem 0.15rem 0;'>tmueller</td><td>Benutzer</td></tr>"
                    . "</table>"
                    . "<small style='display:block; margin-top:0.75rem;'>Bitte Passwörter nach dem ersten Login unter <em>Profil</em> ändern.</small>";
                header("Refresh:10; url=login.php");
            } else {
                $error = "Die .env Datei konnte nicht geschrieben werden. Bitte prüfe die Schreibrechte.";
            }
        } else {
            $error = "Fehler beim Anlegen der Tabellen: " . $migration;
        }
    } else {
        $error = "Datenbankverbindung fehlgeschlagen: " . $test;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <style>
        body { justify-content: center; align-items: center; background: radial-gradient(circle at top right, #1e293b, var(--bg-dark)); }
        .setup-card { max-width: 500px; width: 100%; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .form-control:focus { border-color: var(--primary-color); }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .alert-error { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald); border: 1px solid rgba(16, 185, 129, 0.2); }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <div class="card setup-card">
        <h1 style="margin-bottom: 0.5rem;">Willkommen</h1>
        <p style="color: var(--text-muted); margin-bottom: 2rem;">Konfiguriere deine Datenbank für Mini-Snipe.</p>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label>DB Host</label>
                    <input type="text" name="db_host" class="form-control" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>DB Name (muss existieren)</label>
                    <input type="text" name="db_name" class="form-control" placeholder="z.B. asset_management" required>
                </div>
                <div class="form-group">
                    <label>DB User</label>
                    <input type="text" name="db_user" class="form-control" value="root" required>
                </div>
                <div class="form-group">
                    <label>DB Passwort</label>
                    <input type="password" name="db_pass" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Setup starten</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
