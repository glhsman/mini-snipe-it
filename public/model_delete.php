<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireAdmin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: settings.php');
    exit;
}

$modelId = (int)$_GET['id'];
$db = Database::getInstance();
$masterData = new MasterDataController($db);

try {
    if ($masterData->deleteAssetModel($modelId)) {
        header('Location: settings.php?success=' . urlencode('Das Asset Modell wurde erfolgreich gelöscht.'));
    } else {
        header('Location: settings.php?error=' . urlencode('Fehler beim Löschen des Modells.'));
    }
} catch (\PDOException $e) {
    if ($e->getCode() == '23000') {
        header('Location: settings.php?error=' . urlencode('Dieses Asset Modell kann nicht gelöscht werden, da noch Assets existieren, die diesem Modell zugeordnet sind.'));
    } else {
        header('Location: settings.php?error=' . urlencode('Ein unerwarteter Datenbankfehler ist aufgetreten.'));
    }
}
exit;
