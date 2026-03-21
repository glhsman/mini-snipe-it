<?php

namespace App\Helpers;

class Mail {
    public static function getConfig(): array {
        return [
            'smtp_host' => trim((string) getenv('MAIL_HOST')),
            'smtp_port' => (string) (getenv('MAIL_PORT') !== false ? getenv('MAIL_PORT') : ''),
            'smtp_ssl' => strtolower(trim((string) (getenv('MAIL_ENCRYPTION') !== false ? getenv('MAIL_ENCRYPTION') : 'tls'))),
            'auth_username' => trim((string) (getenv('MAIL_USER') !== false ? getenv('MAIL_USER') : '')),
            'auth_password' => (string) (getenv('MAIL_PASS') !== false ? getenv('MAIL_PASS') : ''),
            'from_address' => trim((string) (getenv('MAIL_FROM_ADDRESS') !== false ? getenv('MAIL_FROM_ADDRESS') : '')),
            'from_name' => trim((string) (getenv('MAIL_FROM_NAME') !== false ? getenv('MAIL_FROM_NAME') : '')),
        ];
    }

    public static function sendTextMail(string $toEmail, string $subject, string $message): array {
        return self::sendMail($toEmail, $subject, $message, false);
    }

    public static function sendHtmlMail(string $toEmail, string $subject, string $htmlMessage, string $textMessage = ''): array {
        return self::sendMail($toEmail, $subject, $htmlMessage, true, $textMessage);
    }

    private static function sendMail(
        string $toEmail,
        string $subject,
        string $message,
        bool $isHtml,
        string $textAlternative = ''
    ): array {
        $cfg = self::getConfig();

        if (!class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            $composerAutoload = __DIR__ . '/../../vendor/autoload.php';
            if (is_file($composerAutoload)) {
                require_once $composerAutoload;
            }
        }

        if (class_exists('\\PHPMailer\\PHPMailer\\PHPMailer')) {
            return self::sendWithPHPMailer($toEmail, $subject, $message, $cfg, $isHtml, $textAlternative);
        }

        return self::sendDirect($toEmail, $subject, $message, $cfg, $isHtml, $textAlternative);
    }

    private static function readResponse($socket): string {
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

    private static function sendCommand($socket, string $command, array $okCodes): array {
        fwrite($socket, $command . "\r\n");
        $response = self::readResponse($socket);
        $code = (int) substr($response, 0, 3);

        return [
            'ok' => in_array($code, $okCodes, true),
            'response' => trim($response),
            'code' => $code,
        ];
    }

    private static function resolveFrom(array $cfg): array {
        $username = trim((string) ($cfg['auth_username'] ?? ''));
        $fromAddress = trim((string) ($cfg['from_address'] ?? ''));
        $fromName = trim((string) ($cfg['from_name'] ?? ''));

        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
            $fromAddress = filter_var($username, FILTER_VALIDATE_EMAIL) ? $username : 'no-reply@localhost';
        }

        if ($fromName === '') {
            $fromName = 'Mini-Snipe';
        }

        return [
            'address' => $fromAddress,
            'name' => $fromName,
        ];
    }

    private static function sendDirect(
        string $toEmail,
        string $subject,
        string $message,
        array $cfg,
        bool $isHtml = false,
        string $textAlternative = ''
    ): array {
        $host = trim((string) ($cfg['smtp_host'] ?? ''));
        $port = (int) ($cfg['smtp_port'] ?? 0);
        $username = trim((string) ($cfg['auth_username'] ?? ''));
        $password = (string) ($cfg['auth_password'] ?? '');
        $sslMode = strtolower(trim((string) ($cfg['smtp_ssl'] ?? 'auto')));

        if ($host === '' || $port <= 0) {
            return ['success' => false, 'message' => 'SMTP-Konfiguration unvollständig: Host oder Port fehlt.'];
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
            return ['success' => false, 'message' => "SMTP-Verbindung fehlgeschlagen ({$host}:{$port}): {$errstr} ({$errno})"];
        }

        stream_set_timeout($socket, 20);
        $greeting = self::readResponse($socket);
        if ((int) substr($greeting, 0, 3) !== 220) {
            fclose($socket);
            return ['success' => false, 'message' => 'SMTP-Server meldet keinen gültigen Start: ' . trim($greeting)];
        }

        $heloHost = gethostname() ?: 'localhost';
        $ehlo = self::sendCommand($socket, 'EHLO ' . $heloHost, [250]);
        if (!$ehlo['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => 'EHLO fehlgeschlagen: ' . $ehlo['response']];
        }

        $supportsStartTls = stripos($ehlo['response'], 'STARTTLS') !== false;
        if ($transport === 'tcp' && $sslMode !== 'none' && ($sslMode === 'tls' || $sslMode === 'auto' || $sslMode === 'starttls')) {
            if (!$supportsStartTls) {
                fclose($socket);
                return ['success' => false, 'message' => 'Server bietet kein STARTTLS an, aber TLS ist konfiguriert.'];
            }

            $startTls = self::sendCommand($socket, 'STARTTLS', [220]);
            if (!$startTls['ok']) {
                fclose($socket);
                return ['success' => false, 'message' => 'STARTTLS fehlgeschlagen: ' . $startTls['response']];
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
                return ['success' => false, 'message' => 'TLS-Handshake fehlgeschlagen (stream_socket_enable_crypto).'];
            }

            $ehloTls = self::sendCommand($socket, 'EHLO ' . $heloHost, [250]);
            if (!$ehloTls['ok']) {
                fclose($socket);
                return ['success' => false, 'message' => 'EHLO nach STARTTLS fehlgeschlagen: ' . $ehloTls['response']];
            }
        }

        if ($username !== '' || $password !== '') {
            $auth = self::sendCommand($socket, 'AUTH LOGIN', [334]);
            if (!$auth['ok']) {
                fclose($socket);
                return ['success' => false, 'message' => 'AUTH LOGIN wurde abgelehnt: ' . $auth['response']];
            }

            $authUser = self::sendCommand($socket, base64_encode($username), [334]);
            if (!$authUser['ok']) {
                fclose($socket);
                return ['success' => false, 'message' => 'SMTP-Benutzername abgelehnt: ' . $authUser['response']];
            }

            $authPass = self::sendCommand($socket, base64_encode($password), [235]);
            if (!$authPass['ok']) {
                fclose($socket);
                return ['success' => false, 'message' => 'SMTP-Passwort abgelehnt: ' . $authPass['response']];
            }
        }

        $from = self::resolveFrom($cfg);
        $fromAddress = $from['address'];
        $fromName = $from['name'];
        $mailFrom = self::sendCommand($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
        if (!$mailFrom['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => 'MAIL FROM abgelehnt: ' . $mailFrom['response']];
        }

        $rcptTo = self::sendCommand($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251]);
        if (!$rcptTo['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => 'Empfaenger abgelehnt: ' . $rcptTo['response']];
        }

        $data = self::sendCommand($socket, 'DATA', [354]);
        if (!$data['ok']) {
            fclose($socket);
            return ['success' => false, 'message' => 'DATA-Befehl fehlgeschlagen: ' . $data['response']];
        }

        $headers = [
            'From: ' . $fromName . ' <' . $fromAddress . '>',
            'To: ' . $toEmail,
            'Subject: ' . $subject,
            'Reply-To: ' . $fromAddress,
            'X-Mailer: Mini-Snipe SMTP',
            'MIME-Version: 1.0',
        ];

        if ($isHtml) {
            $boundary = '=_MiniSnipe_' . bin2hex(random_bytes(12));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $textBody = trim($textAlternative) !== '' ? $textAlternative : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
            $textBody = str_replace(["\r\n", "\r"], "\n", $textBody);
            $htmlBody = str_replace(["\r\n", "\r"], "\n", $message);
            $body = '--' . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . str_replace("\n.", "\n..", str_replace("\n", "\r\n", $textBody)) . "\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: 8bit\r\n\r\n"
                . str_replace("\n.", "\n..", str_replace("\n", "\r\n", $htmlBody)) . "\r\n"
                . '--' . $boundary . "--\r\n";
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $body = str_replace(["\r\n", "\r"], "\n", $message);
            $body = str_replace("\n.", "\n..", $body);
            $body = str_replace("\n", "\r\n", $body) . "\r\n";
        }

        $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body . ".\r\n";
        fwrite($socket, $payload);

        $queued = self::readResponse($socket);
        $queuedCode = (int) substr($queued, 0, 3);
        self::sendCommand($socket, 'QUIT', [221]);
        fclose($socket);

        if (!in_array($queuedCode, [250], true)) {
            return ['success' => false, 'message' => 'Server hat Nachricht nicht akzeptiert: ' . trim($queued)];
        }

        return ['success' => true, 'message' => "Mail erfolgreich versendet ({$host}:{$port})."];
    }

    private static function sendWithPHPMailer(
        string $toEmail,
        string $subject,
        string $message,
        array $cfg,
        bool $isHtml = false,
        string $textAlternative = ''
    ): array {
        $host = trim((string) ($cfg['smtp_host'] ?? ''));
        $port = (int) ($cfg['smtp_port'] ?? 0);
        $username = trim((string) ($cfg['auth_username'] ?? ''));
        $password = (string) ($cfg['auth_password'] ?? '');
        $sslMode = strtolower(trim((string) ($cfg['smtp_ssl'] ?? 'auto')));

        if ($host === '' || $port <= 0) {
            return ['success' => false, 'message' => 'SMTP-Konfiguration unvollständig: Host oder Port fehlt.'];
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

            $from = self::resolveFrom($cfg);
            $mail->setFrom($from['address'], $from['name']);
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->isHTML($isHtml);
            $mail->Body = $message;
            if ($isHtml) {
                $mail->AltBody = trim($textAlternative) !== '' ? $textAlternative : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message)));
            }
            $mail->send();

            return ['success' => true, 'message' => "Mail erfolgreich versendet ({$host}:{$port})."];
        } catch (\Throwable $e) {
            $errorInfo = (string) ($mail->ErrorInfo ?? '');
            $reason = $errorInfo !== '' ? $errorInfo : $e->getMessage();
            return ['success' => false, 'message' => "Mailversand fehlgeschlagen ({$host}:{$port}): {$reason}"];
        }
    }
}
