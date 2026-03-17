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

$manufacturerId = (int)$_GET['id'];
$db = Database::getInstance();
$masterData = new MasterDataController($db);

try {
    if ($masterData->deleteManufacturer($manufacturerId)) {
        header('Location: settings.php?success=' . urlencode('Der Hersteller wurde erfolgreich gelöscht.'));
    } else {
        header('Location: settings.php?error=' . urlencode('Fehler beim Löschen des Herstellers.'));
    }
} catch (\PDOException $e) {
    if ($e->getCode() == '23000') {
        header('Location: settings.php?error=' . urlencode('Dieser Hersteller kann nicht gelöscht werden, da er noch von Asset Modellen verwendet wird.'));
    } else {
        header('Location: settings.php?error=' . urlencode('Ein unerwarteter Datenbankfehler ist aufgetreten.'));
    }
}
exit;
