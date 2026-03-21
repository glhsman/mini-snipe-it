<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Controllers/AssetRequestController.php';

use App\Controllers\AssetRequestController;
use App\Controllers\MasterDataController;

$db = Database::getInstance();
$masterData = new MasterDataController($db);
$requestController = new AssetRequestController($db);

$locations = $masterData->getLocations();
$categories = $masterData->getCategories();

$errors = [];
$formData = [
    'location_id' => '',
    'username' => '',
    'category_id' => '',
    'quantity' => '1',
    'reason' => '',
];

if (!isset($_SESSION['asset_request_csrf'])) {
    $_SESSION['asset_request_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['asset_request_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['location_id'] = trim((string) ($_POST['location_id'] ?? ''));
    $formData['username'] = trim((string) ($_POST['username'] ?? ''));
    $formData['category_id'] = trim((string) ($_POST['category_id'] ?? ''));
    $formData['quantity'] = trim((string) ($_POST['quantity'] ?? '1'));
    $formData['reason'] = trim((string) ($_POST['reason'] ?? ''));

    $postedCsrf = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($csrfToken, $postedCsrf)) {
        $errors[] = 'Sitzung ist abgelaufen. Bitte Formular erneut absenden.';
    }

    // Simple honeypot against bots.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        $errors[] = 'Absenden konnte nicht verarbeitet werden.';
    }

    $locationId = (int) $formData['location_id'];
    $categoryId = (int) $formData['category_id'];
    $quantity = (int) $formData['quantity'];
    $reason = $formData['reason'];
    $username = $formData['username'];

    if ($locationId <= 0) {
        $errors[] = 'Bitte einen Standort auswählen.';
    }
    if ($username === '') {
        $errors[] = 'Bitte den Benutzernamen angeben.';
    }
    if ($categoryId <= 0) {
        $errors[] = 'Bitte eine Kategorie auswählen.';
    }
    if ($quantity < 1 || $quantity > 20) {
        $errors[] = 'Bitte eine gültige Anzahl zwischen 1 und 20 angeben.';
    }
    if ($reason === '') {
        $errors[] = 'Bitte eine Begruendung eintragen.';
    }

    if (empty($errors)) {
        $result = $requestController->createPublicRequest($locationId, $username, $categoryId, $quantity, $reason);
        if (!empty($result['success'])) {
            $_SESSION['asset_request_success'] = true;
            header('Location: asset_request_public.php?submitted=1');
            exit;
        }
        $errors[] = (string) ($result['error'] ?? 'Anforderung konnte nicht gespeichert werden.');
    }
}

$success = isset($_GET['submitted']) && $_GET['submitted'] === '1' && !empty($_SESSION['asset_request_success']);
if ($success) {
    unset($_SESSION['asset_request_success']);
    $formData = [
        'location_id' => '',
        'username' => '',
        'category_id' => '',
        'quantity' => '1',
        'reason' => '',
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <?php include_once __DIR__ . '/includes/head_favicon.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geraet anfordern - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
    <style>
        body {
            justify-content: center;
            align-items: center;
            background: radial-gradient(circle at top right, #1e293b, var(--bg-dark));
            min-height: 100vh;
        }
        .request-card {
            width: min(760px, 96vw);
        }
        .request-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .request-grid .full {
            grid-column: 1 / -1;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.45rem;
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid var(--glass-border);
            color: var(--text-main);
            outline: none;
        }
        textarea.form-control {
            min-height: 130px;
            resize: vertical;
        }
        .alert {
            padding: 0.9rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            border: 1px solid transparent;
        }
        .alert-error {
            background: rgba(244, 63, 94, 0.12);
            color: #fecdd3;
            border-color: rgba(244, 63, 94, 0.35);
        }
        .alert-success {
            background: rgba(16, 185, 129, 0.14);
            color: #d1fae5;
            border-color: rgba(16, 185, 129, 0.35);
        }
        .sr-only {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            border: 0;
        }
        .card-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        .hint {
            color: var(--text-muted);
            font-size: 0.82rem;
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-main);
            text-decoration: none;
            border: 1px solid var(--glass-border);
        }
        @media (max-width: 820px) {
            .request-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <div class="card request-card">
        <h1 style="margin-bottom: 0.4rem;">Neues Geraet anfordern</h1>
        <p style="color: var(--text-muted); margin-bottom: 1.25rem;">Bitte geben Sie die erforderlichen Daten ein. Die Anforderung wird intern geprueft.</p>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">Ihre Anforderung wurde gespeichert und an das Asset-Management übermittelt.</div>
        <?php endif; ?>

        <form method="POST" action="asset_request_public.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
            <label for="website" class="sr-only">Website</label>
            <input type="text" id="website" name="website" class="sr-only" autocomplete="off" tabindex="-1">

            <div class="request-grid">
                <div class="form-group">
                    <label for="location_id">Standort *</label>
                    <select id="location_id" name="location_id" class="form-control" required>
                                <option value="">Bitte wählen</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?php echo (int) $location['id']; ?>" <?php echo $formData['location_id'] === (string) $location['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $location['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="username">Benutzername *</label>
                    <input id="username" type="text" name="username" class="form-control" required maxlength="100" value="<?php echo htmlspecialchars($formData['username'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="form-group">
                    <label for="category_id">Kategorie *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">Bitte wählen</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo (int) $category['id']; ?>" <?php echo $formData['category_id'] === (string) $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="quantity">Anzahl *</label>
                    <input id="quantity" type="number" name="quantity" min="1" max="20" class="form-control" required value="<?php echo htmlspecialchars($formData['quantity'], ENT_QUOTES, 'UTF-8'); ?>">
                </div>

                <div class="form-group full">
                    <label for="reason">Begruendung *</label>
                    <textarea id="reason" name="reason" class="form-control" required maxlength="3000"><?php echo htmlspecialchars($formData['reason'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
            </div>

            <div class="card-actions">
                    <a href="login.php" class="btn btn-secondary">Zurück zum Login</a>
                <button type="submit" class="btn btn-primary">Anforderung absenden</button>
            </div>
                        <p class="hint">* Pflichtfelder. Die Anforderung wird nur gespeichert, wenn Benutzername und Standort zusammen eindeutig zugeordnet werden können.</p>
        </form>
    </div>
</body>
</html>
