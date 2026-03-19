<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetRequestController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetRequestController;
use App\Helpers\Auth;

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
    header('Location: asset_requests.php?success=1');
} else {
    header('Location: asset_requests.php?error=update');
}
exit;
