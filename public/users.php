<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/UserController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\UserController;
use App\Helpers\Auth;

Auth::requireLogin();

$db = Database::getInstance();
$userController = new UserController($db);

$allowedPerPage = [25, 50, 100, 250];
$perPage = isset($_GET['per_page']) && in_array((int)$_GET['per_page'], $allowedPerPage, true)
    ? (int)$_GET['per_page'] : 25;

if (isset($_GET['q'])) {
    $search = trim($_GET['q']);
    $_SESSION['users_search'] = $search;
} elseif (isset($_SESSION['users_search'])) {
    $search = $_SESSION['users_search'];
} else {
    $search = '';
}

// Fallback fuer aeltere Deployments ohne Pagination-Methoden im Controller.
if (method_exists($userController, 'countUsersFiltered') && method_exists($userController, 'getUsersPaginatedFiltered')) {
    $totalUsers = $userController->countUsersFiltered($search);
    $totalPages = max(1, (int) ceil($totalUsers / $perPage));
    $page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $totalPages)) : 1;
    $offset = ($page - 1) * $perPage;
    $users = $userController->getUsersPaginatedFiltered($search, $perPage, $offset);
} else {
    $allUsers = $userController->getAllUsers();
    $totalUsers = count($allUsers);
    $totalPages = max(1, (int) ceil($totalUsers / $perPage));
    $page = isset($_GET['page']) ? max(1, min((int)$_GET['page'], $totalPages)) : 1;
    $offset = ($page - 1) * $perPage;
    $users = array_slice($allUsers, $offset, $perPage);
}

function usersPaginationUrl($p, $pp) {
    $params = $_GET;
    $params['page'] = $p;
    $params['per_page'] = $pp;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benutzer - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <?php $activePage = 'users'; include_once __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="header">
            <h1>Benutzerverwaltung</h1>
            <?php if (Auth::isAdmin()): ?>
                <a href="user_create.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Benutzer anlegen</a>
            <?php endif; ?>
        </header>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">Benutzer erfolgreich gelöscht.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="card">
            <!-- Suchzeile -->
            <form method="GET" action="users.php" style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap; padding: 1rem 0 1.25rem 0;">
                <div style="position:relative; flex:1; min-width:200px;">
                    <i class="fas fa-search" style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--text-muted);"></i>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Name, Benutzername, E-Mail oder Standort ..."
                        style="width:100%; padding:0.6rem 0.75rem 0.6rem 2.25rem; border-radius:0.5rem; background:rgba(0,0,0,0.2); border:1px solid var(--glass-border); color:white; outline:none; font-size:0.875rem; box-sizing:border-box;">
                </div>
                <input type="hidden" name="per_page" value="<?php echo $perPage; ?>">
                <button type="submit" class="btn btn-primary" style="padding:0.6rem 1rem; font-size:0.875rem;"><i class="fas fa-filter"></i> Suchen</button>
                <?php if ($search !== ''): ?>
                    <a href="users.php?q=&per_page=<?php echo $perPage; ?>" style="padding:0.6rem 0.75rem; border-radius:0.5rem; background:rgba(255,255,255,0.07); border:1px solid var(--glass-border); color:var(--text-muted); text-decoration:none; font-size:0.875rem; white-space:nowrap;">
                        <i class="fas fa-times"></i> Filter zurücksetzen
                    </a>
                <?php endif; ?>
            </form>

            <!-- Zeile: Treffer + Pro-Seite -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem; padding-bottom:1rem;">
                <p style="color:var(--text-muted); font-size:0.875rem; margin:0;">
                    <?php $from = $totalUsers ? $offset + 1 : 0; $to = min($offset + $perPage, $totalUsers); ?>
                    Zeige <strong><?php echo $from; ?></strong>&ndash;<strong><?php echo $to; ?></strong> von <strong><?php echo $totalUsers; ?></strong> Benutzern
                    <?php if ($search !== ''): ?><span style="color:var(--accent-rose);"> (gefiltert)</span><?php endif; ?>
                </p>
                <div style="display:flex; align-items:center; gap: 0.5rem;">
                    <span style="color: var(--text-muted); font-size: 0.875rem;">Pro Seite:</span>
                    <?php foreach ($allowedPerPage as $opt): ?>
                        <a href="<?php echo htmlspecialchars(usersPaginationUrl(1, $opt)); ?>"
                           style="padding: 0.25rem 0.6rem; border-radius: 0.375rem; font-size: 0.8rem; text-decoration:none;
                                  <?php echo $opt === $perPage ? 'background: var(--primary-color); color: white;' : 'background: rgba(255,255,255,0.07); color: var(--text-muted); border: 1px solid var(--glass-border);'; ?>">
                            <?php echo $opt; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <table class="data-table" id="userTable">
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
                        <td><?php echo htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')); ?></td>
                        <td>
                            <?php if (!array_key_exists('can_login', $user) || (int) ($user['can_login'] ?? 0) === 1): ?>
                                <span title="Web-Login erlaubt" aria-label="Web-Login erlaubt" style="display:inline-block; margin-right:0.35rem; color:#38bdf8; font-size:0.9rem; line-height:1; vertical-align:middle;">🌐</span>
                            <?php endif; ?>
                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($user['location_name'] ?? '-'); ?></td>
                        <td>
                            <?php if (Auth::isEditor()): ?>
                                    <a href="user_edit.php?id=<?php echo $user['id']; ?>" class="btn-icon" title="Bearbeiten">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" action="user_delete.php" style="display:inline;" onsubmit="return confirm('Möchten Sie den Benutzer \'<?php echo htmlspecialchars(addslashes($user['username'])); ?>\' wirklich löschen?');">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" title="Löschen" class="btn-icon btn-icon-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                            <?php else: ?>
                                <span class="btn-icon" title="Ansehen"><i class="fas fa-eye"></i></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
            <div style="display:flex; justify-content:center; align-items:center; gap: 0.4rem; padding: 1.5rem 0 0.5rem;">
                <?php if ($page > 1): ?>
                    <a href="<?php echo htmlspecialchars(usersPaginationUrl(1, $perPage)); ?>" style="padding:0.35rem 0.65rem; border-radius:0.375rem; background:rgba(255,255,255,0.07); border:1px solid var(--glass-border); color:var(--text-muted); text-decoration:none; font-size:0.8rem;" title="Erste Seite"><i class="fas fa-angle-double-left"></i></a>
                    <a href="<?php echo htmlspecialchars(usersPaginationUrl($page - 1, $perPage)); ?>" style="padding:0.35rem 0.65rem; border-radius:0.375rem; background:rgba(255,255,255,0.07); border:1px solid var(--glass-border); color:var(--text-muted); text-decoration:none; font-size:0.8rem;"><i class="fas fa-angle-left"></i></a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++): ?>
                    <a href="<?php echo htmlspecialchars(usersPaginationUrl($i, $perPage)); ?>"
                       style="padding:0.35rem 0.65rem; border-radius:0.375rem; font-size:0.8rem; text-decoration:none; min-width:2rem; text-align:center;
                              <?php echo $i === $page ? 'background: var(--primary-color); color: white;' : 'background: rgba(255,255,255,0.07); color: var(--text-muted); border: 1px solid var(--glass-border);'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?php echo htmlspecialchars(usersPaginationUrl($page + 1, $perPage)); ?>" style="padding:0.35rem 0.65rem; border-radius:0.375rem; background:rgba(255,255,255,0.07); border:1px solid var(--glass-border); color:var(--text-muted); text-decoration:none; font-size:0.8rem;"><i class="fas fa-angle-right"></i></a>
                    <a href="<?php echo htmlspecialchars(usersPaginationUrl($totalPages, $perPage)); ?>" style="padding:0.35rem 0.65rem; border-radius:0.375rem; background:rgba(255,255,255,0.07); border:1px solid var(--glass-border); color:var(--text-muted); text-decoration:none; font-size:0.8rem;" title="Letzte Seite"><i class="fas fa-angle-double-right"></i></a>
                <?php endif; ?>
            </div>
            <p style="text-align:center; color:var(--text-muted); font-size:0.8rem; padding-bottom:0.75rem;">Seite <?php echo $page; ?> von <?php echo $totalPages; ?></p>
            <?php endif; ?>
        </div>
    </main>
    <!-- JavaScript-Filterung entfernt, da nun serverseitig -->
</body>
</html>

