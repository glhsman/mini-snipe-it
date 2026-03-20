<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetRequestController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';
require_once __DIR__ . '/../src/Helpers/Mail.php';

use App\Controllers\AssetRequestController;
use App\Helpers\Auth;
use App\Helpers\Mail;

Auth::requireEditor();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: asset_requests.php');
    exit;
}

$postedCsrf = (string) ($_POST['csrf_token'] ?? '');
$sessionCsrf = (string) ($_SESSION['asset_requests_admin_csrf'] ?? '');
if ($sessionCsrf === '' || !hash_equals($sessionCsrf, $postedCsrf)) {
    header('Location: asset_requests.php?error=csrf');
    exit;
}

$requestId = (int) ($_POST['request_id'] ?? 0);
$status = (string) ($_POST['status'] ?? '');
$note = trim((string) ($_POST['internal_note'] ?? ''));

$db = Database::getInstance();
$requestController = new AssetRequestController($db);
$updated = $requestController->updateRequestStatus($requestId, $status, $note, (int) Auth::getUserId());

if ($updated) {
    $mailStatus = '';
    if (in_array($status, ['in_progress', 'rejected'], true)) {
        $requestRow = $requestController->getRequestByIdWithUser($requestId);
        if ($requestRow && !empty($requestRow['email']) && filter_var((string) $requestRow['email'], FILTER_VALIDATE_EMAIL)) {
            $siteName = 'Mini-Snipe';
            try {
                $settingsStmt = $db->query("SELECT site_name FROM settings WHERE id = 1 LIMIT 1");
                $settings = $settingsStmt ? $settingsStmt->fetch() : null;
                $resolvedSiteName = trim((string) ($settings['site_name'] ?? ''));
                if ($resolvedSiteName !== '') {
                    $siteName = $resolvedSiteName;
                }
            } catch (\Throwable $e) {
                // Fallback auf Default-Site-Namen
            }

            $displayName = trim((string) (($requestRow['first_name'] ?? '') . ' ' . ($requestRow['last_name'] ?? '')));
            if ($displayName === '') {
                $displayName = (string) ($requestRow['username'] ?? '');
            }
            if ($displayName === '') {
                $displayName = 'Benutzer';
            }

            $categoryName = (string) ($requestRow['category_name'] ?? '');
            $locationName = (string) ($requestRow['location_name'] ?? '');
            $quantity = (int) ($requestRow['quantity'] ?? 0);
            $cleanNote = trim((string) $requestController->stripInProgressPrefix($note));

            if ($status === 'in_progress') {
                $subject = $siteName . ' - Statusupdate zu Ihrer Hardware-Anforderung';
                $message = "Hallo " . $displayName . ",\n\n"
                    . "Ihre Hardware-Anforderung (#" . $requestId . ") wird jetzt bearbeitet (Status: In Arbeit).\n\n"
                    . "Kategorie: " . $categoryName . "\n"
                    . "Anzahl: " . $quantity . "\n"
                    . "Standort: " . $locationName . "\n";
                if ($cleanNote !== '') {
                    $message .= "\nInterne Notiz: " . $cleanNote . "\n";
                }
                $message .= "\nViele Gruesse\n" . $siteName;
                $mailResult = Mail::sendTextMail((string) $requestRow['email'], $subject, $message);
                $mailStatus = !empty($mailResult['success']) ? 'sent' : 'failed';
            } elseif ($status === 'rejected') {
                $subject = $siteName . ' - Ihre Hardware-Anforderung wurde abgelehnt';
                $message = "Hallo " . $displayName . ",\n\n"
                    . "Ihre Hardware-Anforderung (#" . $requestId . ") wurde abgelehnt.\n\n"
                    . "Kategorie: " . $categoryName . "\n"
                    . "Anzahl: " . $quantity . "\n"
                    . "Standort: " . $locationName . "\n";
                if ($cleanNote !== '') {
                    $message .= "\nHinweis: " . $cleanNote . "\n";
                }
                $message .= "\nViele Gruesse\n" . $siteName;
                $mailResult = Mail::sendTextMail((string) $requestRow['email'], $subject, $message);
                $mailStatus = !empty($mailResult['success']) ? 'sent' : 'failed';
            }
        } else {
            $mailStatus = 'no_email';
        }
    }

    $redirectUrl = 'asset_requests.php?success=1';
    if ($mailStatus !== '') {
        $redirectUrl .= '&mail=' . urlencode($mailStatus);
    }
    header('Location: ' . $redirectUrl);
} else {
    header('Location: asset_requests.php?error=update');
}
exit;
