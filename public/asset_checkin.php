<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Helpers\Auth;

Auth::requireEditor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $assetController = new AssetController($db);

    if (isset($_POST['asset_ids']) && is_array($_POST['asset_ids'])) {
        $assetIds = array_values(array_unique(array_filter(array_map('intval', $_POST['asset_ids']), static fn($id) => $id > 0)));
        $historyIds = [];

        foreach ($assetIds as $assetId) {
            try {
                $historyIds[] = $assetController->checkinAsset($assetId, Auth::getUserId());
            } catch (\Throwable $e) {
                // Einzelne Fehler blockieren nicht die restlichen Ruecknahmen.
            }
        }

        if (!empty($historyIds)) {
            $query = 'type=return&history_ids=' . implode(',', $historyIds);
            $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
            if ($userId > 0) {
                $query .= '&id=' . $userId;
            }
            header('Location: user_protocol.php?' . $query);
            exit;
        }
    } elseif (isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        try {
            $assignmentId = $assetController->checkinAsset($id, Auth::getUserId());
            header('Location: user_protocol.php?type=return&history_id=' . $assignmentId);
            exit;
        } catch (\Throwable $e) {
        }
    }
}

// Zurück zur vorherigen Seite
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'assets.php'));
exit;
