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
    
    if ($assetController->deleteAsset($id)) {
        header('Location: assets.php?success=deleted');
    } else {
        header('Location: assets.php?error=delete_failed');
    }
    exit;
} else {
    header('Location: assets.php');
    exit;
}
