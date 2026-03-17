<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\UserController;
use App\Helpers\Auth;

Auth::requireLogin();

$db = Database::getInstance();
$userController = new UserController($db);
$users = $userController->getAllUsers();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzer - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div class="logo">Mini-Snipe</div>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="assets.php" class="nav-link"><i class="fas fa-laptop"></i> Assets</a>
            <a href="users.php" class="nav-link active"><i class="fas fa-users"></i> User</a>
            <?php if (Auth::isAdmin()): ?>
                <a href="locations.php" class="nav-link"><i class="fas fa-map-marker-alt"></i> Standorte</a>
                <a href="settings.php" class="nav-link"><i class="fas fa-cog"></i> Einstellungen</a>
            <?php endif; ?>
            <a href="logout.php" class="nav-link" style="margin-top: 2rem; border-top: 1px solid var(--glass-border); padding-top: 1.5rem;"><i class="fas fa-sign-out-alt"></i> Abmelden</a>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <h1>Benutzerverwaltung</h1>
            <?php if (Auth::isAdmin()): ?>
                <button class="btn btn-primary"><i class="fas fa-user-plus"></i> Benutzer anlegen</button>
            <?php endif; ?>
        </header>

        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Benutzername</th>
                        <th>E-Mail</th>
                        <th>Standort</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['location_name'] ?? '-'); ?></td>
                        <td>
                            <?php if (Auth::isEditor()): ?>
                                <a href="user_edit.php?id=<?php echo $user['id']; ?>" style="color: inherit; text-decoration: none;">
                                    <i class="fas fa-edit" style="cursor:pointer; margin-right: 10px;"></i>
                                </a>
                                <i class="fas fa-trash" style="cursor:pointer; color: var(--accent-rose);"></i>
                            <?php else: ?>
                                <i class="fas fa-eye" style="cursor:pointer; color: var(--text-muted);"></i>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>

