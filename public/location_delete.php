<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireAdmin();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: locations.php');
    exit;
}

$locationId = (int)$_GET['id'];
$db = Database::getInstance();
$masterData = new MasterDataController($db);

try {
    if ($masterData->deleteLocation($locationId)) {
        header('Location: locations.php?success=' . urlencode('Der Standort wurde erfolgreich gelöscht.'));
    } else {
        header('Location: locations.php?error=' . urlencode('Fehler beim Löschen des Standorts.'));
    }
} catch (\PDOException $e) {
    if ($e->getCode() == '23000') {
        header('Location: locations.php?error=' . urlencode('Dieser Standort kann nicht gelöscht werden, da er noch mit bestehenden Benutzern oder Assets verknüpft ist.'));
    } else {
        header('Location: locations.php?error=' . urlencode('Ein unerwarteter Datenbankfehler ist aufgetreten.'));
    }
}
exit;
