<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';
require_once __DIR__ . '/../src/Helpers/Mail.php';

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

use App\Helpers\Auth;
use App\Helpers\Mail;
use App\Helpers\Settings;

Auth::requireAdmin();

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function getSendmailConfig() {
    $envPath = realpath(__DIR__ . '/../.env') ?: (__DIR__ . '/../.env');
    $smtp_host = trim((string) getenv('MAIL_HOST'));
    $smtp_port = (string) (getenv('MAIL_PORT') !== false ? getenv('MAIL_PORT') : '');
    $smtp_ssl = strtolower(trim((string) (getenv('MAIL_ENCRYPTION') !== false ? getenv('MAIL_ENCRYPTION') : '')));
    $auth_username = trim((string) (getenv('MAIL_USER') !== false ? getenv('MAIL_USER') : ''));
    $auth_password = (string) (getenv('MAIL_PASS') !== false ? getenv('MAIL_PASS') : '');
    
    return [
        'env_path' => $envPath,
        'smtp_host' => $smtp_host,
        'smtp_port' => $smtp_port,
        'smtp_ssl' => $smtp_ssl !== '' ? $smtp_ssl : 'tls',
        'smtp_source' => '.env',
        'auth_username' => $auth_username,
        'auth_password' => $auth_password,
    ];
}

function smtpReadResponse($socket): string {
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (strlen($line) < 4 || $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtpSendCommand($socket, string $command, array $okCodes): array {
    fwrite($socket, $command . "\r\n");
    $response = smtpReadResponse($socket);
    $code = (int) substr($response, 0, 3);
    return [
        'ok' => in_array($code, $okCodes, true),
        'response' => trim($response),
        'code' => $code,
    ];
}

function testSmtpDirect(string $to_email, string $subject, string $message, array $cfg): array {
    $host = trim((string) ($cfg['smtp_host'] ?? ''));
    $port = (int) ($cfg['smtp_port'] ?? 0);
    $username = trim((string) ($cfg['auth_username'] ?? ''));
    $password = (string) ($cfg['auth_password'] ?? '');
    $sslMode = strtolower(trim((string) ($cfg['smtp_ssl'] ?? 'auto')));

    if ($host === '' || $port <= 0) {
        return [
            'success' => false,
            'message' => 'SMTP-Konfiguration unvollstaendig: Host oder Port fehlt.',
        ];
    }

    $transport = ($sslMode === 'ssl' || $port === 465) ? 'ssl' : 'tcp';
    $target = $transport . '://' . $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $socket = @stream_socket_client($target, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return [
            'success' => false,
            'message' => "SMTP-Verbindung fehlgeschlagen ({$host}:{$port}): {$errstr} ({$errno})",
        ];
    }

    stream_set_timeout($socket, 20);
    $greeting = smtpReadResponse($socket);
    if ((int) substr($greeting, 0, 3) !== 220) {
        fclose($socket);
        return [
            'success' => false,
            'message' => 'SMTP-Server meldet keinen gueltigen Start: ' . trim($greeting),
        ];
    }

    $heloHost = gethostname() ?: 'localhost';
    $ehlo = smtpSendCommand($socket, 'EHLO ' . $heloHost, [250]);
    if (!$ehlo['ok']) {
        fclose($socket);
        return [
            'success' => false,
            'message' => 'EHLO fehlgeschlagen: ' . $ehlo['response'],
        ];
    }

    $supportsStartTls = stripos($ehlo['response'], 'STARTTLS') !== false;
    if ($transport === 'tcp' && $sslMode !== 'none' && ($sslMode === 'tls' || $sslMode === 'auto')) {
        if (!$supportsStartTls) {
            fclose($socket);
            return [
                'success' => false,
                'message' => 'Server bietet kein STARTTLS an, aber TLS ist konfiguriert.',
            ];
        }

        $startTls = smtpSendCommand($socket, 'STARTTLS', [220]);
        if (!$startTls['ok']) {
            fclose($socket);
            return [
                'success' => false,
                'message' => 'STARTTLS fehlgeschlagen: ' . $startTls['response'],
            ];
        }

        $cryptoMethod = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')
            ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            : STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $cryptoMethod |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }

        $cryptoOk = @stream_socket_enable_crypto($socket, true, $cryptoMethod);
        if ($cryptoOk !== true) {
            fclose($socket);
            return [
                'success' => false,
                'message' => 'TLS-Handshake fehlgeschlagen (stream_socket_enable_crypto).',
            ];
        }

        $ehloTls = smtpSendCommand($socket, 'EHLO ' . $heloHost, [250]);
        if (!$ehloTls['ok']) {
            fclose($socket);
            return [
                'success' => false,
                'message' => 'EHLO nach STARTTLS fehlgeschlagen: ' . $ehloTls['response'],
            ];
        }
    }

    if ($username !== '' || $password !== '') {
        $auth = smtpSendCommand($socket, 'AUTH LOGIN', [334]);
        if (!$auth['ok']) {
            fclose($socket);
            return [
                'success' => false,
                'message' => 'AUTH LOGIN wurde abgelehnt: ' . $auth['response'],
            ];
        }

        $authUser = smtpSendCommand($socket, base64_encode($username), [334]);
        if (!$authUser['ok']) {
            fclose($socket);
            return [
                'success' => false,
                'message' => 'SMTP-Benutzername abgelehnt: ' . $authUser['response'],
            ];
        }

        $authPass = smtpSendCommand($socket, base64_encode($password), [235]);
        if (!$authPass['ok']) {
            fclose($socket);
            return [
                'success' => false,
                'message' => 'SMTP-Passwort abgelehnt: ' . $authPass['response'],
            ];
        }
    }

    $fromAddress = filter_var($username, FILTER_VALIDATE_EMAIL) ? $username : 'no-reply@localhost';
    $mailFrom = smtpSendCommand($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
    if (!$mailFrom['ok']) {
        fclose($socket);
        return [
            'success' => false,
            'message' => 'MAIL FROM abgelehnt: ' . $mailFrom['response'],
        ];
    }

    $rcptTo = smtpSendCommand($socket, 'RCPT TO:<' . $to_email . '>', [250, 251]);
    if (!$rcptTo['ok']) {
        fclose($socket);
        return [
            'success' => false,
            'message' => 'Empfaenger abgelehnt: ' . $rcptTo['response'],
        ];
    }

    $data = smtpSendCommand($socket, 'DATA', [354]);
    if (!$data['ok']) {
        fclose($socket);
        return [
            'success' => false,
            'message' => 'DATA-Befehl fehlgeschlagen: ' . $data['response'],
        ];
    }

    $headers = [
        'From: ' . $fromAddress,
        'To: ' . $to_email,
        'Subject: ' . $subject,
        'Reply-To: ' . $fromAddress,
        'Content-Type: text/plain; charset=UTF-8',
        'X-Mailer: Mini-Snipe SMTP',
    ];
    $body = str_replace(["\r\n", "\r"], "\n", $message);
    $body = str_replace("\n.", "\n..", $body);
    $payload = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body) . "\r\n.\r\n";
    fwrite($socket, $payload);

    $queued = smtpReadResponse($socket);
    $queuedCode = (int) substr($queued, 0, 3);
    smtpSendCommand($socket, 'QUIT', [221]);
    fclose($socket);

    if (!in_array($queuedCode, [250], true)) {
        return [
            'success' => false,
            'message' => 'Server hat Nachricht nicht akzeptiert: ' . trim($queued),
        ];
    }

    return [
        'success' => true,
        'message' => "SMTP-Test erfolgreich. Server hat die Nachricht akzeptiert ({$host}:{$port}).",
    ];
}

function buildTlsHostnameHint(string $reason): string {
    if (preg_match("/CN=`([^']+)' did not match expected CN=`([^']+)'/", $reason, $matches)) {
        $certName = trim((string) ($matches[1] ?? ''));
        $expectedName = trim((string) ($matches[2] ?? ''));
        if ($certName !== '' && $expectedName !== '') {
            return " Hinweis: Zertifikat-Hostname passt nicht. Server-Zertifikat ist fuer '{$certName}', konfiguriert ist '{$expectedName}'. Setze smtp_server={$certName} oder fordere ein passendes Zertifikat beim Provider an.";
        }
    }
    return '';
}

function testSmtpWithPHPMailer(string $to_email, string $subject, string $message, array $cfg): array {
    if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
        return [
            'success' => false,
            'message' => 'PHPMailer nicht verfuegbar (vendor/autoload.php nicht gefunden).',
        ];
    }

    $host = trim((string) ($cfg['smtp_host'] ?? ''));
    $port = (int) ($cfg['smtp_port'] ?? 0);
    $username = trim((string) ($cfg['auth_username'] ?? ''));
    $password = (string) ($cfg['auth_password'] ?? '');
    $sslMode = strtolower(trim((string) ($cfg['smtp_ssl'] ?? 'auto')));

    if ($host === '' || $port <= 0) {
        return [
            'success' => false,
            'message' => 'SMTP-Konfiguration unvollstaendig: Host oder Port fehlt.',
        ];
    }

    $pmClass = '\\PHPMailer\\PHPMailer\\PHPMailer';
    $mail = new $pmClass(true);

    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = $port;
        $mail->Timeout = 20;
        $mail->CharSet = 'UTF-8';

        $mail->SMTPAuth = ($username !== '' || $password !== '');
        if ($mail->SMTPAuth) {
            $mail->Username = $username;
            $mail->Password = $password;
        }

        // Port 465 uses implicit TLS, otherwise STARTTLS if enabled.
        if ($sslMode === 'ssl' || $port === 465) {
            $mail->SMTPSecure = $pmClass::ENCRYPTION_SMTPS;
            $mail->SMTPAutoTLS = false;
        } elseif ($sslMode === 'none') {
            $mail->SMTPSecure = '';
            $mail->SMTPAutoTLS = false;
        } else {
            $mail->SMTPSecure = $pmClass::ENCRYPTION_STARTTLS;
            $mail->SMTPAutoTLS = true;
        }

        $fromAddress = filter_var($username, FILTER_VALIDATE_EMAIL) ? $username : 'no-reply@localhost';
        $mail->setFrom($fromAddress, 'Mini-Snipe');
        $mail->addAddress($to_email);
        $mail->Subject = $subject;
        $mail->Body = $message;

        $mail->send();

        return [
            'success' => true,
            'message' => "SMTP-Test erfolgreich mit PHPMailer ({$host}:{$port}).",
        ];
    } catch (\Throwable $e) {
        $errorInfo = (string) ($mail->ErrorInfo ?? '');
        $reason = $errorInfo !== '' ? $errorInfo : $e->getMessage();
        $hostHint = buildTlsHostnameHint($reason);

        return [
            'success' => false,
            'message' => "PHPMailer Fehler bei {$host}:{$port}: {$reason}{$hostHint}",
        ];
    }
}

/**
 * Test-Mail versenden
 */
function testSendmail($to_email, $subject, $message) {
    return Mail::sendTextMail($to_email, $subject, $message);
}

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

/**
 * Skaliert ein Bild auf exakt 32×32 px und speichert es als PNG (Favicon).
 */
function resizeFavicon($sourceFile, $targetFile) {
    if (!extension_loaded('gd')) {
        return move_uploaded_file($sourceFile, $targetFile);
    }

    $info = getimagesize($sourceFile);
    if (!$info) return false;

    $mime = $info['mime'];
    switch ($mime) {
        case 'image/jpeg': $src = @imagecreatefromjpeg($sourceFile); break;
        case 'image/png':  $src = @imagecreatefrompng($sourceFile); break;
        case 'image/gif':  $src = @imagecreatefromgif($sourceFile); break;
        case 'image/webp': $src = @imagecreatefromwebp($sourceFile); break;
        default: return move_uploaded_file($sourceFile, $targetFile);
    }

    if (!$src) return move_uploaded_file($sourceFile, $targetFile);

    $dst = imagecreatetruecolor(32, 32);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefill($dst, 0, 0, $transparent);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, 32, 32, $info[0], $info[1]);
    $success = @imagepng($dst, $targetFile);
    imagedestroy($src);
    imagedestroy($dst);
    return $success;
}

$db = Database::getInstance();
\App\Helpers\Settings::load($db);

// Einstellungen laden
$settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();

$error = null;
$success = null;
$testMailResult = null;

// Test-Mail-Versand
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_sendmail'])) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $postedToken)) {
        $error = 'Ungueltiges Formular-Token. Bitte Seite neu laden.';
    } else {
        $testEmail = trim($_POST['test_email'] ?? '');
        if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Bitte eine gueltige E-Mail-Adresse eingeben.';
        } else {
            $testMailResult = testSendmail(
                $testEmail,
                'Mini-Snipe Sendmail Test',
                "Hallo,\n\nDies ist eine Test-E-Mail von Mini-Snipe.\n\nWenn Sie diese Nachricht erhalten haben, funktioniert Ihr E-Mail-System korrekt.\n\nViele Gruesse,\nIhr Mini-Snipe System"
            );
            if ($testMailResult['success']) {
                $stmt = $db->prepare("UPDATE settings SET mail_test_success_at = NOW(), mail_test_recipient = ?, mail_test_last_error = NULL WHERE id = 1");
                $stmt->execute([$testEmail]);
                $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
                $success = $testMailResult['message'];
            } else {
                $stmt = $db->prepare("UPDATE settings SET mail_test_last_error = ? WHERE id = 1");
                $stmt->execute([$testMailResult['message']]);
                $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
                $error = $testMailResult['message'];
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $postedToken)) {
        $error = 'Ungueltiges Formular-Token. Bitte Seite neu laden.';
    }

    $siteName = $_POST['site_name'] ?? 'Mini-Snipe';
    $brandingType = $_POST['branding_type'] ?? 'text';
    $companyAddress = trim($_POST['company_address'] ?? '');
    $protocolHeaderText = trim($_POST['protocol_header_text'] ?? '');
    $protocolFooterText = trim($_POST['protocol_footer_text'] ?? '');
    $logoPath    = $settings['site_logo'];    // Standardwert behalten
    $faviconPath = $settings['site_favicon']; // Standardwert behalten

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

    // Favicon entfernen
    if (isset($_POST['remove_favicon']) && $_POST['remove_favicon'] == '1') {
        $fixedFaviconFile = __DIR__ . '/uploads/favicon/favicon.png';
        if (file_exists($fixedFaviconFile)) {
            unlink($fixedFaviconFile);
        }
        $faviconPath = null;
    }

    // Favicon Upload
    if (!$error && isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] === UPLOAD_ERR_OK) {
        $faviconUploadDir = __DIR__ . '/uploads/favicon/';
        if (!is_dir($faviconUploadDir)) {
            mkdir($faviconUploadDir, 0755, true);
        }
        $faviconExt = strtolower(pathinfo($_FILES['site_favicon']['name'], PATHINFO_EXTENSION));
        $allowedFaviconExt = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'ico'];
        if (in_array($faviconExt, $allowedFaviconExt)) {
            $targetFavicon = $faviconUploadDir . 'favicon.png'; // fester Name, immer überschreiben
            if (resizeFavicon($_FILES['site_favicon']['tmp_name'], $targetFavicon)) {
                $faviconPath = 'uploads/favicon/favicon.png';
            } else {
                $error = "Favicon konnte nicht verarbeitet werden.";
            }
        } else {
            $error = "Favicon: Dateityp nicht erlaubt. Erlaubt sind: JPG, PNG, GIF, WEBP, ICO.";
        }
    }

    if (!$error) {
        $stmt = $db->prepare("UPDATE settings SET site_name = ?, branding_type = ?, site_logo = ?, site_favicon = ?, company_address = ?, protocol_header_text = ?, protocol_footer_text = ? WHERE id = 1");
        if ($stmt->execute([$siteName, $brandingType, $logoPath, $faviconPath, $companyAddress, $protocolHeaderText, $protocolFooterText])) {
            $success = "Einstellungen erfolgreich gespeichert.";
            // Daten neu laden
            $settings = $db->query("SELECT * FROM settings WHERE id = 1")->fetch();
        } else {
            $error = "Fehler beim Speichern der Einstellungen.";
        }
    }
}

// Log-Cleanup Handler
$logCleanupSuccess = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup_logs'])) {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $postedToken)) {
        $error = 'Ungueltiges Formular-Token. Bitte Seite neu laden.';
    } else {
        $keepCount = (int) ($_POST['keep_count'] ?? 10);
        if (in_array($keepCount, [10, 25, 50, 100], true)) {
            try {
                // DELETE Einträge, die älter sind als die keepCount neuesten
                $stmt = $db->prepare("
                    DELETE FROM login_logs 
                    WHERE id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM login_logs 
                            ORDER BY created_at DESC 
                            LIMIT ?
                        ) AS newest
                    )
                ");
                $stmt->execute([$keepCount]);
                $logCleanupSuccess = "Log erfolgreich bereinigt. Die neuesten {$keepCount} Einträge wurden beibehalten.";
            } catch (\Throwable $e) {
                $error = "Fehler beim Bereinigen des Logs: " . $e->getMessage();
            }
        }
    }
}

// Login-Logs laden (neueste 200 Einträge)
$loginLogs = [];
try {
    $loginLogs = $db->query(
    "SELECT l.id, l.username, l.action, l.reason, l.ip_address, l.created_at,
                u.first_name, u.last_name, u.role
           FROM login_logs l
           LEFT JOIN users u ON u.id = l.user_id
          ORDER BY l.created_at DESC
          LIMIT 200"
    )->fetchAll();
} catch (\Throwable $e) {
    // Tabelle existiert noch nicht (erste Ausführung vor Migration)
    $loginLogs = [];
}

$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo Settings::getPageTitle('Globale Einstellungen'); ?></title>
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
        textarea.form-control {
            min-height: 6.5rem;
            resize: vertical;
            line-height: 1.45;
        }
        .form-help {
            display: block;
            color: var(--text-muted);
            font-size: 0.8rem;
            margin-top: 0.45rem;
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
    <?php $activePage = 'settings_general'; include_once __DIR__ . '/includes/sidebar.php'; ?>

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
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                <input type="hidden" name="save_settings" value="1">
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
                    <div class="form-group">
                        <label>Favicon</label>
                        <div>
                            <input type="file" name="site_favicon" id="site_favicon" style="display:none;" accept="image/*" onchange="previewFavicon(this)">
                            <button type="button" class="btn-upload" onclick="document.getElementById('site_favicon').click()">
                                <i class="fas fa-file-upload"></i> Favicon auswählen...
                            </button>
                            <small style="display:block; color:var(--text-muted); margin-top:0.5rem;">Wird serverseitig auf 32&times;32 px skaliert. Erlaubt: JPG, PNG, GIF, WEBP, ICO.</small>

                            <?php $faviconExists = file_exists(__DIR__ . '/uploads/favicon/favicon.png'); ?>
                            <?php if ($faviconExists): ?>
                                <div style="display:flex; align-items:center; gap:1rem; margin-top:0.75rem;">
                                    <div style="width:48px; height:48px; border:1px solid var(--glass-border); border-radius:0.375rem; background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                        <img src="uploads/favicon/favicon.png?v=<?php echo filemtime(__DIR__ . '/uploads/favicon/favicon.png'); ?>" alt="Favicon" style="width:32px; height:32px;" id="favicon_preview">
                                    </div>
                                    <label style="display:flex; gap:0.5rem; align-items:center; cursor:pointer;">
                                        <input type="checkbox" name="remove_favicon" value="1"> Aktuelles Favicon entfernen
                                    </label>
                                </div>
                            <?php else: ?>
                                <div style="display:none; margin-top:0.75rem;" id="favicon_preview_container">
                                    <div style="width:48px; height:48px; border:1px solid var(--glass-border); border-radius:0.375rem; background:rgba(255,255,255,0.05); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                        <img src="" alt="Favicon" style="width:32px; height:32px; object-fit:contain;" id="favicon_preview">
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Firmenadresse</label>
                        <div>
                            <textarea name="company_address" class="form-control" rows="3"><?php echo htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                            <small class="form-help">Mehrzeilig. Dieser Text erscheint oben im Ausdruck.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Header-Text</label>
                        <div>
                            <textarea name="protocol_header_text" class="form-control" rows="6"><?php echo htmlspecialchars($settings['protocol_header_text'] ?? ''); ?></textarea>
                            <small class="form-help">Einleitungstext oberhalb der Asset-Tabelle fuer Ausgabe- und Rueckgabeprotokolle.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Footer-Text</label>
                        <div>
                            <textarea name="protocol_footer_text" class="form-control" rows="2"><?php echo htmlspecialchars($settings['protocol_footer_text'] ?? ''); ?></textarea>
                            <small class="form-help">Kurzer Fusszeilentext fuer Ausdrucke und Ablage.</small>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save" style="margin-right:0.5rem;"></i> Einstellungen speichern</button>
                </div>
            </form>

            <!-- ===== Sendmail-Konfiguration ===== -->
            <div class="form-section" style="margin-top: 2.5rem;">
                <h2><i class="fas fa-envelope" style="margin-right:0.5rem; color:var(--primary-color);"></i> Sendmail-Konfiguration</h2>

                <?php $sendmailConfig = getSendmailConfig(); ?>
                <div style="background: rgba(99,102,241,0.1); border: 1px solid var(--glass-border); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.875rem;">
                        <div>
                            <span style="color: var(--text-muted);">Konfigurationsdatei:</span><br>
                            <code style="color: var(--accent-color); word-break: break-all;"><?php echo htmlspecialchars($sendmailConfig['env_path']); ?></code>
                        </div>
                        <div>
                            <span style="color: var(--text-muted);">SMTP-Host:</span><br>
                            <code style="color: var(--accent-color);"><?php echo htmlspecialchars($sendmailConfig['smtp_host']); ?>:<?php echo htmlspecialchars($sendmailConfig['smtp_port']); ?></code>
                            <div style="margin-top:0.35rem; color: var(--text-muted); font-size: 0.78rem;">Quelle: <?php echo htmlspecialchars($sendmailConfig['smtp_source']); ?></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($success)): ?>
                    <div style="background: rgba(16,185,129,0.1); border: 1px solid #10b981; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: #10b981;">
                        <i class="fas fa-check-circle" style="margin-right:0.5rem;"></i> <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div style="background: rgba(239,68,68,0.1); border: 1px solid #ef4444; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: #ef4444;">
                        <i class="fas fa-exclamation-circle" style="margin-right:0.5rem;"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" style="display: flex; gap: 1rem; align-items: flex-end;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                    <div style="flex: 1;">
                        <label for="test_email" style="display: block; margin-bottom: 0.5rem; font-weight: 500;">Test-E-Mail senden an:</label>
                        <input type="email" id="test_email" name="test_email" class="form-control" placeholder="beispiel@gmail.com" required="required" />
                    </div>
                    <button type="submit" name="test_sendmail" value="1" class="btn btn-primary">
                        <i class="fas fa-paper-plane" style="margin-right:0.5rem;"></i> Test-Mail senden
                    </button>
                </form>
                <small style="display: block; color: var(--text-muted); margin-top: 0.75rem;">Eine Test-E-Mail wird an die angegebene Adresse gesendet. Dies hilft zur Überprüfung der Mail-Konfiguration aus der <code>.env</code>.</small>
            </div>

            <!-- ===== Login-Protokoll ===== -->
            <div class="form-section" style="margin-top: 2.5rem;">
                <h2><i class="fas fa-list-alt" style="margin-right:0.5rem; color:var(--primary-color);"></i> Login-Protokoll</h2>
                <p style="color:var(--text-muted); font-size:0.875rem; margin-bottom:1.25rem;">Die letzten 200 Login-Events inkl. Fehlversuche und gesperrte Anmeldungen.</p>

                <?php if (!empty($logCleanupSuccess)): ?>
                    <div style="background: rgba(16,185,129,0.1); border: 1px solid #10b981; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.5rem; color: #10b981;">
                        <i class="fas fa-check-circle" style="margin-right:0.5rem;"></i> <?php echo htmlspecialchars($logCleanupSuccess); ?>
                    </div>
                <?php endif; ?>

                <!-- Log-Cleanup Button Section -->
                <div style="display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap;">
                    <form method="POST" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
                        <label for="keep_count" style="color: var(--text-muted); font-size: 0.9rem; font-weight: 500;">Behalte die neuesten</label>
                        <select name="keep_count" id="keep_count" class="form-control" style="width: auto; padding: 0.5rem 0.75rem;">
                            <option value="10">10 Einträge</option>
                            <option value="25">25 Einträge</option>
                            <option value="50">50 Einträge</option>
                            <option value="100">100 Einträge</option>
                        </select>
                        <button type="submit" name="cleanup_logs" value="1" class="btn" style="background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid rgba(239,68,68,0.35); padding: 0.5rem 1rem; font-size: 0.875rem; cursor: pointer; border-radius: 0.375rem;">
                            <i class="fas fa-trash-alt" style="margin-right:0.5rem;"></i> Log bereinigen
                        </button>
                        <small style="color: var(--text-muted); font-size: 0.8rem;">Ältere Einträge werden gelöscht.</small>
                    </form>
                </div>

                <?php if (empty($loginLogs)): ?>
                    <p style="color:var(--text-muted); font-size:0.875rem;">Noch keine Einträge vorhanden.</p>
                <?php else: ?>
                <div style="overflow-x:auto;">
                <table class="data-table" style="font-size:0.85rem;">
                    <thead>
                        <tr>
                            <th>Datum / Uhrzeit</th>
                            <th>Aktion</th>
                            <th>Benutzername</th>
                            <th>Name</th>
                            <th>Rolle</th>
                            <th>Grund</th>
                            <th>IP-Adresse</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($loginLogs as $log): ?>
                        <tr>
                            <td style="white-space:nowrap;"><?php echo htmlspecialchars($log['created_at']); ?></td>
                            <td>
                                <?php if ($log['action'] === 'login'): ?>
                                    <span class="badge badge-success"><i class="fas fa-sign-in-alt"></i> Login</span>
                                <?php elseif ($log['action'] === 'login_failed'): ?>
                                    <span class="badge" style="background: rgba(239,68,68,0.18); color: #ef4444; border: 1px solid rgba(239,68,68,0.35);"><i class="fas fa-ban"></i> Login fehlgeschlagen</span>
                                <?php elseif ($log['action'] === 'login_blocked'): ?>
                                    <span class="badge" style="background: rgba(245,158,11,0.18); color: #f59e0b; border: 1px solid rgba(245,158,11,0.35);"><i class="fas fa-lock"></i> Login gesperrt</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><i class="fas fa-sign-out-alt"></i> Logout</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['username']); ?></td>
                            <td><?php echo htmlspecialchars(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars($log['role'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($log['reason'] ?? ''); ?></td>
                            <td style="font-family:monospace; font-size:0.8rem;"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
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
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewFavicon(input) {
            const preview = document.getElementById('favicon_preview');
            const container = document.getElementById('favicon_preview_container');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    if (container) container.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
