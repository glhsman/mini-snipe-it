<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireAdmin();

$db = Database::getInstance();
$masterData = new MasterDataController($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $type = $_POST['type'] ?? '';
    $value = $_POST['value'] ?? '';
    $id = $_POST['id'] ?? null;

    if ($action === 'add' && !empty($type) && !empty($value)) {
        if ($masterData->addLookupOption($type, $value)) {
            header('Location: settings.php?success=Eintrag hinzugefügt.');
        } else {
            header('Location: settings.php?error=Fehler beim Hinzufügen.');
        }
        exit;
    }

    if ($action === 'delete' && !empty($type) && !empty($id)) {
        if ($masterData->deleteLookupOption($type, (int)$id)) {
            header('Location: settings.php?success=Eintrag gelöscht.');
        } else {
            header('Location: settings.php?error=Fehler beim Löschen.');
        }
        exit;
    }
}

header('Location: settings.php');
exit;
