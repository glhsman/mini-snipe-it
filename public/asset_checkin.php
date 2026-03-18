<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/AssetController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\AssetController;
use App\Helpers\Auth;

Auth::requireEditor();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $db = Database::getInstance();
    $assetController = new AssetController($db);
    $id = (int)$_POST['id'];

    $asset = $assetController->getAssetById($id);
    if ($asset) {
        $asset['user_id'] = null; // Rücknahme: Nutzer entfernen
        $assetController->updateAsset($id, $asset);
    }
}

// Zurück zur vorherigen Seite
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'assets.php'));
exit;
