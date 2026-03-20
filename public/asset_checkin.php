<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Helpers\Auth;

Auth::requireEditor();

function buildReturnRedirectUrl(int $userId = 0, string $status = '', string $message = ''): string {
    $query = [];
    if ($userId > 0) {
        $query['id'] = $userId;
    }
    if ($status !== '') {
        $query['return_status'] = $status;
    }
    if ($message !== '') {
        $query['return_message'] = $message;
    }

    return 'user_edit.php' . (!empty($query) ? '?' . http_build_query($query) : '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $assetController = new AssetController($db);
    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

    if (isset($_POST['asset_ids']) && is_array($_POST['asset_ids'])) {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $_POST['asset_ids']), static fn($id) => $id > 0)));
        $historyIds = [];
        $errors = [];

        if (empty($assetIds)) {
            header('Location: ' . buildReturnRedirectUrl($userId, 'error', 'Bitte mindestens ein Asset fuer die Rueckgabe auswaehlen.'));
            exit;
        }

        foreach ($assetIds as $assetId) {
            try {
                $historyIds[] = $assetController->checkinAsset($assetId, Auth::getUserId());
            } catch (\Throwable $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (!empty($historyIds)) {
            $query = 'type=return&history_ids=' . implode(',', $historyIds);
            if ($userId > 0) {
                $query .= '&id=' . $userId;
            }
            header('Location: user_protocol.php?' . $query);
            exit;
        }

        $message = !empty($errors) ? $errors[0] : 'Rueckgabe konnte nicht verarbeitet werden.';
        header('Location: ' . buildReturnRedirectUrl($userId, 'error', $message));
        exit;
    }

    if (isset($_POST['id'])) {
        $id = (int) $_POST['id'];
        try {
            $assignmentId = $assetController->checkinAsset($id, Auth::getUserId());
            header('Location: user_protocol.php?type=return&history_id=' . $assignmentId);
            exit;
        } catch (\Throwable $e) {
            header('Location: ' . buildReturnRedirectUrl($userId, 'error', $e->getMessage()));
            exit;
        }
    }
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? buildReturnRedirectUrl()));
exit;
