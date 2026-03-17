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

$categoryId = (int)$_GET['id'];
$db = Database::getInstance();
$masterData = new MasterDataController($db);

try {
    if ($masterData->deleteCategory($categoryId)) {
        header('Location: settings.php?success=' . urlencode('Die Kategorie wurde erfolgreich gelöscht.'));
    } else {
        header('Location: settings.php?error=' . urlencode('Fehler beim Löschen der Kategorie.'));
    }
} catch (\PDOException $e) {
    // Fehler 1451: Cannot delete or update a parent row: a foreign key constraint fails
    if ($e->getCode() == '23000') {
        header('Location: settings.php?error=' . urlencode('Diese Kategorie kann nicht gelöscht werden, da sie noch von Asset Modellen verwendet wird.'));
    } else {
        header('Location: settings.php?error=' . urlencode('Ein unerwarteter Datenbankfehler ist aufgetreten.'));
    }
}
exit;
