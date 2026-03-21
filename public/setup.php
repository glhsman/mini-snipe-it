<?php
require_once __DIR__ . '/../src/Controllers/SetupController.php';

use App\Controllers\SetupController;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$setup = new SetupController();
$error = null;
$success = null;
$stage = 'input';

if ($setup->isInstalled()) {
    header('Location: index.php');
    exit;
}

if (!isset($_SESSION['setup_wizard_csrf']) || !is_string($_SESSION['setup_wizard_csrf']) || $_SESSION['setup_wizard_csrf'] === '') {
    $_SESSION['setup_wizard_csrf'] = bin2hex(random_bytes(32));
}

function h($value): string {
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function getPostedSetupData(): array {
    return [
        'db_host' => trim((string) ($_POST['db_host'] ?? 'localhost')),
        'db_name' => trim((string) ($_POST['db_name'] ?? '')),
        'db_user' => trim((string) ($_POST['db_user'] ?? 'root')),
        'db_pass' => (string) ($_POST['db_pass'] ?? ''),
        'mail_host' => trim((string) ($_POST['mail_host'] ?? '')),
        'mail_port' => trim((string) ($_POST['mail_port'] ?? '587')),
        'mail_encryption' => trim((string) ($_POST['mail_encryption'] ?? 'tls')),
        'mail_user' => trim((string) ($_POST['mail_user'] ?? '')),
        'mail_pass' => (string) ($_POST['mail_pass'] ?? ''),
        'mail_from_address' => trim((string) ($_POST['mail_from_address'] ?? '')),
        'mail_from_name' => trim((string) ($_POST['mail_from_name'] ?? 'Mini-Snipe')),
    ];
}

function hasValidSetupData(array $data): bool {
    return $data['db_host'] !== '' && $data['db_name'] !== '' && $data['db_user'] !== '';
}

function hasValidMailData(array $data): bool {
    $mailHost = trim((string) ($data['mail_host'] ?? ''));
    $mailPort = trim((string) ($data['mail_port'] ?? ''));
    $mailUser = trim((string) ($data['mail_user'] ?? ''));
    $mailPass = (string) ($data['mail_pass'] ?? '');
    $mailFromAddress = trim((string) ($data['mail_from_address'] ?? ''));
    $mailFromName = trim((string) ($data['mail_from_name'] ?? ''));

    $mailConfigured = $mailHost !== ''
        || $mailUser !== ''
        || $mailPass !== ''
        || $mailFromAddress !== ''
        || ($mailFromName !== '' && $mailFromName !== 'Mini-Snipe');

    if (!$mailConfigured) {
        return true;
    }

    if ($mailHost === '' || $mailPort === '') {
        return false;
    }

    $encryption = trim((string) ($data['mail_encryption'] ?? 'tls'));
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        return false;
    }

    return ctype_digit($mailPort);
}

$data = [
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => 'root',
    'db_pass' => '',
    'mail_host' => '',
    'mail_port' => '587',
    'mail_encryption' => 'tls',
    'mail_user' => '',
    'mail_pass' => '',
    'mail_from_address' => '',
    'mail_from_name' => 'Mini-Snipe',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');
    $sessionCsrf = (string) ($_SESSION['setup_wizard_csrf'] ?? '');
    if ($sessionCsrf === '' || !hash_equals($sessionCsrf, $postedCsrf)) {
        $error = 'Sicherheitspruefung fehlgeschlagen. Bitte Setup erneut starten.';
        $stage = 'input';
    } else {
        $action = (string) ($_POST['setup_action'] ?? 'review_connection');
        $data = getPostedSetupData();

        if ($action === 'cancel_setup') {
            $stage = 'input';
            $data['db_pass'] = '';
            $data['mail_pass'] = '';
        } elseif (!hasValidSetupData($data)) {
            $error = 'Bitte DB Host, DB Name und DB User vollstaendig angeben.';
            $stage = 'input';
        } elseif (!hasValidMailData($data)) {
            $error = 'Mail-Konfiguration ungueltig. Wenn du Maildaten eintraegst, muessen Host, numerischer Port und eine gueltige Verschluesselungsart gesetzt sein.';
            $stage = 'input';
        } else {
            if ($action === 'review_connection') {
                $stage = 'review_connection';
            } elseif ($action === 'run_connection_test') {
                $test = $setup->testConnection($data);
                if ($test === true) {
                    $stage = 'review_migration';
                } else {
                    $error = 'Datenbankverbindung fehlgeschlagen: ' . $test;
                    if (stripos($test, 'pdo_mysql') !== false || stripos($test, 'could not find driver') !== false) {
                        $error .= ' Bitte auf dem Zielsystem die PHP-Erweiterung pdo_mysql bzw. den MySQL-Treiber aktivieren.';
                    }
                    $stage = 'review_connection';
                }
            } elseif ($action === 'run_migrations') {
                $test = $setup->testConnection($data);
                if ($test !== true) {
                    $error = 'Datenbankverbindung fehlgeschlagen: ' . $test;
                    if (stripos($test, 'pdo_mysql') !== false || stripos($test, 'could not find driver') !== false) {
                        $error .= ' Bitte auf dem Zielsystem die PHP-Erweiterung pdo_mysql bzw. den MySQL-Treiber aktivieren.';
                    }
                    $stage = 'review_connection';
                } else {
                    $migration = $setup->runMigrations($data);
                    if ($migration === true) {
                        $stage = 'review_config_save';
                    } else {
                        $error = 'Fehler beim Anlegen der Tabellen: ' . $migration;
                        $stage = 'review_migration';
                    }
                }
            } elseif ($action === 'run_config_save') {
                $test = $setup->testConnection($data);
                if ($test !== true) {
                    $error = 'Datenbankverbindung fehlgeschlagen: ' . $test;
                    if (stripos($test, 'pdo_mysql') !== false || stripos($test, 'could not find driver') !== false) {
                        $error .= ' Bitte auf dem Zielsystem die PHP-Erweiterung pdo_mysql bzw. den MySQL-Treiber aktivieren.';
                    }
                    $stage = 'review_connection';
                } else {
                    $saveResult = $setup->saveConfig($data);
                    if ($saveResult) {
                        $success = "Installation erfolgreich! Alle Tabellen und Demo-Daten wurden angelegt.<br><br>"
                            . "<strong>Demo-Zugangsdaten (Passwort ueberall: <code>password</code>)</strong><br>"
                            . "<table style='margin-top:0.5rem; border-collapse:collapse; font-size:0.85rem;'>"
                            . "<tr><th style='text-align:left; padding:0.2rem 1rem 0.2rem 0; color:inherit;'>Benutzername</th><th style='text-align:left; padding:0.2rem 1rem 0.2rem 0; color:inherit;'>Rolle</th></tr>"
                            . "<tr><td style='padding:0.15rem 1rem 0.15rem 0;'><strong>admin</strong></td><td>Administrator</td></tr>"
                            . "<tr><td style='padding:0.15rem 1rem 0.15rem 0;'>mmuster</td><td>Editor</td></tr>"
                            . "<tr><td style='padding:0.15rem 1rem 0.15rem 0;'>aschmidt</td><td>Benutzer</td></tr>"
                            . "<tr><td style='padding:0.15rem 1rem 0.15rem 0;'>tmueller</td><td>Benutzer</td></tr>"
                            . "</table>"
                            . "<small style='display:block; margin-top:0.75rem;'>Bitte Passwoerter nach dem ersten Login unter <em>Profil</em> aendern.</small>";
                        header('Refresh:10; url=login.php');
                    } else {
                        $error = 'Die .env Datei konnte nicht geschrieben werden. Bitte pruefe die Schreibrechte.';
                        $stage = 'review_config_save';
                    }
                }
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
    <title>Setup - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <style>
        body { justify-content: center; align-items: center; background: radial-gradient(circle at top right, #1e293b, var(--bg-dark)); }
        .setup-card { max-width: 620px; width: 100%; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .form-control:focus { border-color: var(--primary-color); }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .alert-error { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald); border: 1px solid rgba(16, 185, 129, 0.2); }
        .step-list { display: grid; gap: 0.75rem; margin-bottom: 1.5rem; }
        .step-item { padding: 0.85rem 1rem; border: 1px solid var(--glass-border); border-radius: 0.5rem; background: rgba(255,255,255,0.04); }
        .step-item strong { display: block; margin-bottom: 0.25rem; }
        .stop-marker { margin-bottom: 1.5rem; padding: 1rem; border-radius: 0.75rem; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); }
        .stop-marker h2 { margin: 0 0 0.5rem; font-size: 1rem; color: #fcd34d; }
        .setup-summary { margin-bottom: 1.5rem; padding: 1rem; border: 1px solid var(--glass-border); border-radius: 0.5rem; background: rgba(255,255,255,0.04); }
        .setup-summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.75rem 1rem; }
        .setup-summary-label { display: block; margin-bottom: 0.25rem; color: var(--text-muted); font-size: 0.8rem; }
        .setup-summary-value { font-family: monospace; word-break: break-word; }
        .button-row { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.25rem; }
        .btn-secondary { background: rgba(255,255,255,0.08); border: 1px solid var(--glass-border); color: white; }
        .password-mask { letter-spacing: 0.15em; }
        .section-title { margin: 0 0 1rem; font-size: 1rem; color: var(--text-muted); }
        @media (max-width: 640px) {
            .setup-summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <div class="card setup-card">
        <h1 style="margin-bottom: 0.5rem;">Willkommen</h1>
        <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Konfiguriere deine Datenbank fuer Mini-Snipe. Vor jedem kritischen Schritt gibt es jetzt einen separaten Stopp-Punkt.</p>

        <div class="step-list">
            <div class="step-item">
                <strong>1. Verbindung pruefen</strong>
                Zugangsdaten kontrollieren und Verbindung zur vorhandenen Datenbank explizit testen.
            </div>
            <div class="step-item">
                <strong>2. Tabellen und Vorbelegungen anlegen</strong>
                Erst nach separater Freigabe werden Schema und Seed-Daten geschrieben.
            </div>
            <div class="step-item">
                <strong>3. Konfiguration speichern</strong>
                Die `.env` wird erst ganz am Ende geschrieben.
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php elseif ($stage === 'input'): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['setup_wizard_csrf']); ?>">
                <input type="hidden" name="setup_action" value="review_connection">
                <div class="form-group">
                    <label>DB Host</label>
                    <input type="text" name="db_host" class="form-control" value="<?php echo h($data['db_host']); ?>" required>
                </div>
                <div class="form-group">
                    <label>DB Name (muss existieren)</label>
                    <input type="text" name="db_name" class="form-control" placeholder="z.B. asset_management" value="<?php echo h($data['db_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>DB User</label>
                    <input type="text" name="db_user" class="form-control" value="<?php echo h($data['db_user']); ?>" required>
                </div>
                <div class="form-group">
                    <label>DB Passwort</label>
                    <input type="password" name="db_pass" class="form-control" value="<?php echo h($data['db_pass']); ?>">
                </div>
                <h2 class="section-title">Mailversand (optional)</h2>
                <div class="form-group">
                    <label>SMTP Host</label>
                    <input type="text" name="mail_host" class="form-control" value="<?php echo h($data['mail_host']); ?>" placeholder="z.B. smtp.example.com">
                </div>
                <div class="form-group">
                    <label>SMTP Port</label>
                    <input type="text" name="mail_port" class="form-control" value="<?php echo h($data['mail_port']); ?>" placeholder="587">
                </div>
                <div class="form-group">
                    <label>Verschluesselung</label>
                    <select name="mail_encryption" class="form-control">
                        <option value="tls" <?php echo $data['mail_encryption'] === 'tls' ? 'selected' : ''; ?>>STARTTLS / TLS</option>
                        <option value="ssl" <?php echo $data['mail_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                        <option value="none" <?php echo $data['mail_encryption'] === 'none' ? 'selected' : ''; ?>>Unverschluesselt</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>SMTP Benutzer</label>
                    <input type="text" name="mail_user" class="form-control" value="<?php echo h($data['mail_user']); ?>" placeholder="optional">
                </div>
                <div class="form-group">
                    <label>SMTP Passwort</label>
                    <input type="password" name="mail_pass" class="form-control" value="<?php echo h($data['mail_pass']); ?>">
                </div>
                <div class="form-group">
                    <label>Absender-Adresse</label>
                    <input type="email" name="mail_from_address" class="form-control" value="<?php echo h($data['mail_from_address']); ?>" placeholder="z.B. no-reply@example.com">
                </div>
                <div class="form-group">
                    <label>Absender-Name</label>
                    <input type="text" name="mail_from_name" class="form-control" value="<?php echo h($data['mail_from_name']); ?>" placeholder="Mini-Snipe">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Weiter zum ersten Stopp</button>
            </form>
        <?php else: ?>
            <div class="setup-summary">
                <div class="setup-summary-grid">
                    <div>
                        <span class="setup-summary-label">DB Host</span>
                        <div class="setup-summary-value"><?php echo h($data['db_host']); ?></div>
                    </div>
                    <div>
                        <span class="setup-summary-label">DB Name</span>
                        <div class="setup-summary-value"><?php echo h($data['db_name']); ?></div>
                    </div>
                    <div>
                        <span class="setup-summary-label">DB User</span>
                        <div class="setup-summary-value"><?php echo h($data['db_user']); ?></div>
                    </div>
                    <div>
                        <span class="setup-summary-label">DB Passwort</span>
                        <div class="setup-summary-value password-mask"><?php echo $data['db_pass'] !== '' ? '********' : '(leer)'; ?></div>
                    </div>
                    <div>
                        <span class="setup-summary-label">SMTP Host</span>
                        <div class="setup-summary-value"><?php echo h($data['mail_host'] !== '' ? $data['mail_host'] : '(nicht gesetzt)'); ?></div>
                    </div>
                    <div>
                        <span class="setup-summary-label">SMTP Port</span>
                        <div class="setup-summary-value"><?php echo h($data['mail_host'] !== '' ? $data['mail_port'] : '(nicht gesetzt)'); ?></div>
                    </div>
                    <div>
                        <span class="setup-summary-label">Verschluesselung</span>
                        <div class="setup-summary-value"><?php echo h($data['mail_host'] !== '' ? $data['mail_encryption'] : '(nicht gesetzt)'); ?></div>
                    </div>
                    <div>
                        <span class="setup-summary-label">SMTP Benutzer</span>
                        <div class="setup-summary-value"><?php echo h($data['mail_user'] !== '' ? $data['mail_user'] : '(nicht gesetzt)'); ?></div>
                    </div>
                    <div>
                        <span class="setup-summary-label">SMTP Passwort</span>
                        <div class="setup-summary-value password-mask"><?php echo $data['mail_pass'] !== '' ? '********' : '(leer)'; ?></div>
                    </div>
                    <div>
                        <span class="setup-summary-label">Absender-Adresse</span>
                        <div class="setup-summary-value"><?php echo h($data['mail_from_address'] !== '' ? $data['mail_from_address'] : '(nicht gesetzt)'); ?></div>
                    </div>
                    <div>
                        <span class="setup-summary-label">Absender-Name</span>
                        <div class="setup-summary-value"><?php echo h($data['mail_from_name'] !== '' ? $data['mail_from_name'] : '(nicht gesetzt)'); ?></div>
                    </div>
                </div>
            </div>

            <?php if ($stage === 'review_connection'): ?>
                <div class="stop-marker">
                    <h2>Stopp vor Schritt 1</h2>
                    <p style="margin:0;">Ab hier wird erstmals aktiv auf die angegebene Datenbank zugegriffen. Wenn die Zugangsdaten oder das Zielsystem nicht sicher korrekt sind, jetzt abbrechen.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['setup_wizard_csrf']); ?>">
                    <input type="hidden" name="setup_action" value="run_connection_test">
                    <input type="hidden" name="db_host" value="<?php echo h($data['db_host']); ?>">
                    <input type="hidden" name="db_name" value="<?php echo h($data['db_name']); ?>">
                    <input type="hidden" name="db_user" value="<?php echo h($data['db_user']); ?>">
                    <input type="hidden" name="db_pass" value="<?php echo h($data['db_pass']); ?>">
                    <input type="hidden" name="mail_host" value="<?php echo h($data['mail_host']); ?>">
                    <input type="hidden" name="mail_port" value="<?php echo h($data['mail_port']); ?>">
                    <input type="hidden" name="mail_encryption" value="<?php echo h($data['mail_encryption']); ?>">
                    <input type="hidden" name="mail_user" value="<?php echo h($data['mail_user']); ?>">
                    <input type="hidden" name="mail_pass" value="<?php echo h($data['mail_pass']); ?>">
                    <input type="hidden" name="mail_from_address" value="<?php echo h($data['mail_from_address']); ?>">
                    <input type="hidden" name="mail_from_name" value="<?php echo h($data['mail_from_name']); ?>">
                    <div class="button-row">
                        <button type="submit" class="btn btn-primary">Verbindung jetzt testen</button>
                    </div>
                </form>
            <?php elseif ($stage === 'review_migration'): ?>
                <div class="alert alert-success">Verbindung zur Datenbank wurde erfolgreich getestet.</div>
                <div class="stop-marker">
                    <h2>Stopp vor Schritt 2</h2>
                    <p style="margin:0;">Der naechste Schritt legt Tabellen, Indizes und Vorbelegungen in der ausgewaehlten Datenbank an. Wenn die Datenbank doch nicht leer oder nicht fuer dieses System vorgesehen ist, hier abbrechen.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['setup_wizard_csrf']); ?>">
                    <input type="hidden" name="setup_action" value="run_migrations">
                    <input type="hidden" name="db_host" value="<?php echo h($data['db_host']); ?>">
                    <input type="hidden" name="db_name" value="<?php echo h($data['db_name']); ?>">
                    <input type="hidden" name="db_user" value="<?php echo h($data['db_user']); ?>">
                    <input type="hidden" name="db_pass" value="<?php echo h($data['db_pass']); ?>">
                    <input type="hidden" name="mail_host" value="<?php echo h($data['mail_host']); ?>">
                    <input type="hidden" name="mail_port" value="<?php echo h($data['mail_port']); ?>">
                    <input type="hidden" name="mail_encryption" value="<?php echo h($data['mail_encryption']); ?>">
                    <input type="hidden" name="mail_user" value="<?php echo h($data['mail_user']); ?>">
                    <input type="hidden" name="mail_pass" value="<?php echo h($data['mail_pass']); ?>">
                    <input type="hidden" name="mail_from_address" value="<?php echo h($data['mail_from_address']); ?>">
                    <input type="hidden" name="mail_from_name" value="<?php echo h($data['mail_from_name']); ?>">
                    <div class="button-row">
                        <button type="submit" class="btn btn-primary">Tabellen jetzt anlegen</button>
                    </div>
                </form>
            <?php elseif ($stage === 'review_config_save'): ?>
                <div class="alert alert-success">Tabellen und Vorbelegungen wurden erfolgreich angelegt.</div>
                <div class="stop-marker">
                    <h2>Stopp vor Schritt 3</h2>
                    <p style="margin:0;">Im letzten Schritt wird die lokale Konfiguration gespeichert. Wenn du die DB-Struktur zuerst manuell pruefen willst, kannst du hier abbrechen und spaeter fortfahren.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['setup_wizard_csrf']); ?>">
                    <input type="hidden" name="setup_action" value="run_config_save">
                    <input type="hidden" name="db_host" value="<?php echo h($data['db_host']); ?>">
                    <input type="hidden" name="db_name" value="<?php echo h($data['db_name']); ?>">
                    <input type="hidden" name="db_user" value="<?php echo h($data['db_user']); ?>">
                    <input type="hidden" name="db_pass" value="<?php echo h($data['db_pass']); ?>">
                    <input type="hidden" name="mail_host" value="<?php echo h($data['mail_host']); ?>">
                    <input type="hidden" name="mail_port" value="<?php echo h($data['mail_port']); ?>">
                    <input type="hidden" name="mail_encryption" value="<?php echo h($data['mail_encryption']); ?>">
                    <input type="hidden" name="mail_user" value="<?php echo h($data['mail_user']); ?>">
                    <input type="hidden" name="mail_pass" value="<?php echo h($data['mail_pass']); ?>">
                    <input type="hidden" name="mail_from_address" value="<?php echo h($data['mail_from_address']); ?>">
                    <input type="hidden" name="mail_from_name" value="<?php echo h($data['mail_from_name']); ?>">
                    <div class="button-row">
                        <button type="submit" class="btn btn-primary">Konfiguration jetzt speichern</button>
                    </div>
                </form>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['setup_wizard_csrf']); ?>">
                <input type="hidden" name="setup_action" value="cancel_setup">
                <input type="hidden" name="db_host" value="<?php echo h($data['db_host']); ?>">
                <input type="hidden" name="db_name" value="<?php echo h($data['db_name']); ?>">
                <input type="hidden" name="db_user" value="<?php echo h($data['db_user']); ?>">
                <input type="hidden" name="db_pass" value="<?php echo h($data['db_pass']); ?>">
                <input type="hidden" name="mail_host" value="<?php echo h($data['mail_host']); ?>">
                <input type="hidden" name="mail_port" value="<?php echo h($data['mail_port']); ?>">
                <input type="hidden" name="mail_encryption" value="<?php echo h($data['mail_encryption']); ?>">
                <input type="hidden" name="mail_user" value="<?php echo h($data['mail_user']); ?>">
                <input type="hidden" name="mail_pass" value="<?php echo h($data['mail_pass']); ?>">
                <input type="hidden" name="mail_from_address" value="<?php echo h($data['mail_from_address']); ?>">
                <input type="hidden" name="mail_from_name" value="<?php echo h($data['mail_from_name']); ?>">
                <div class="button-row">
                    <button type="submit" class="btn btn-secondary">Abbrechen und Eingaben verwerfen</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
