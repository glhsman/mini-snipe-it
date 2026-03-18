<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\UserController;
use App\Helpers\Auth;

Auth::requireEditor();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $db = Database::getInstance();
    $userController = new UserController($db);
    
    $id = (int)$_POST['id'];
    
    // Verhindern, dass man sich selbst löscht
    Auth::startSession();
    if (isset($_SESSION['user_id']) && $id === (int)$_SESSION['user_id']) {
        header('Location: users.php?error=' . urlencode('Sie können sich nicht selbst löschen.'));
        exit;
    }
    
    try {
        if ($userController->deleteUser($id)) {
            header('Location: users.php?success=deleted');
        } else {
            header('Location: users.php?error=delete_failed');
        }
    } catch (\Exception $e) {
        header('Location: users.php?error=' . urlencode($e->getMessage()));
    }
    exit;
} else {
    header('Location: users.php');
    exit;
}
