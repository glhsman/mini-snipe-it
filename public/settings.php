<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireAdmin();

$db = Database::getInstance();
$masterData = new MasterDataController($db);

$error = null;
$success = null;

// GET-Handling für Statusmeldungen
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

$categories = $masterData->getCategories();
$manufacturers = $masterData->getManufacturers();
$statusLabels = $masterData->getStatusLabels();
$models = $masterData->getAssetModels();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-section { margin-bottom: 3rem; }
        .settings-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem; }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            justify-content: center; align-items: center;
            z-index: 1000;
        }
        .modal-overlay.active { display: flex; }
        .modal-card {
            background: #1e293b;
            border: 1px solid var(--glass-border);
            border-radius: 1rem;
            padding: 2rem;
            width: 100%; max-width: 500px;
            box-shadow: var(--glass-shadow);
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .modal-title { font-size: 1.25rem; font-weight: 600; }
        .close-btn { background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.25rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-muted); font-size: 0.875rem; }
        .form-control { width: 100%; padding: 0.75rem; border-radius: 0.5rem; background: rgba(0,0,0,0.2); border: 1px solid var(--glass-border); color: white; outline: none; }
        .form-control:focus { border-color: var(--primary-color); }
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .alert-error { background: rgba(244, 63, 94, 0.1); color: var(--accent-rose); border: 1px solid rgba(244, 63, 94, 0.2); }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--accent-emerald); border: 1px solid rgba(16, 185, 129, 0.2); }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">Mini-Snipe</div>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="assets.php" class="nav-link"><i class="fas fa-laptop"></i> Assets</a>
            <a href="users.php" class="nav-link"><i class="fas fa-users"></i> User</a>
            <a href="locations.php" class="nav-link"><i class="fas fa-map-marker-alt"></i> Standorte</a>
            <a href="settings.php" class="nav-link active"><i class="fas fa-cog"></i> Einstellungen</a>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <h1>Stammdatenverwaltung</h1>
        </header>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="settings-section">
            <div class="settings-header">
                <h2>Asset Modelle</h2>
                <a href="model_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i></a>
            </div>
            <div class="card">
                <table class="data-table">
                    <thead><tr><th>Name</th><th>Hersteller</th><th>Kategorie</th><th>Modellnummer</th><th>Aktionen</th></tr></thead>
                    <tbody>
                        <?php if (empty($models)): ?>
                            <tr><td colspan="6" style="text-align:center;">Keine Modelle vorhanden.</td></tr>
                        <?php else: ?>
                            <?php foreach ($models as $model): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($model['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($model['manufacturer_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($model['category_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($model['model_number'] ?? '-'); ?></td>
                                <td>
                                    <a href="model_edit.php?id=<?php echo $model['id']; ?>" style="color: white; text-decoration: none; margin-right: 15px;"><i class="fas fa-edit"></i></a>
                                    <a href="model_delete.php?id=<?php echo $model['id']; ?>" style="color: var(--accent-rose); text-decoration: none;" onclick="return confirm('Möchten Sie das Modell \'<?php echo addslashes($model['name']); ?>\' wirklich löschen?');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <div class="settings-section">
                <div class="settings-header">
                    <h2>Kategorien</h2>
                    <a href="category_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i></a>
                </div>
                <div class="card">
                    <table class="data-table">
                        <tbody>
                            <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><span class="badge badge-success" style="font-family:monospace;"><?php echo htmlspecialchars($cat['kuerzel'] ?? '-'); ?></span></td>
                                <td><strong><?php echo htmlspecialchars($cat['name']); ?></strong></td>
                                <td align="right">
                                    <a href="category_edit.php?id=<?php echo $cat['id']; ?>" style="color: white; text-decoration: none; margin-right: 15px;"><i class="fas fa-edit"></i></a>
                                    <a href="category_delete.php?id=<?php echo $cat['id']; ?>" style="color: var(--accent-rose); text-decoration: none;" onclick="return confirm('Möchten Sie die Kategorie \'<?php echo addslashes($cat['name']); ?>\' wirklich löschen?');"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="settings-section">
                <div class="settings-header">
                    <h2>Hersteller</h2>
                    <a href="manufacturer_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i></a>
                </div>
                <div class="card">
                    <table class="data-table">
                        <tbody>
                            <?php foreach ($manufacturers as $man): ?>
                            <tr><td><?php echo htmlspecialchars($man['name']); ?></td><td align="right"><a href="manufacturer_edit.php?id=<?php echo $man['id']; ?>" style="color: white; text-decoration: none; margin-right: 15px;"><i class="fas fa-edit"></i></a><a href="manufacturer_delete.php?id=<?php echo $man['id']; ?>" style="color: var(--accent-rose); text-decoration: none;" onclick="return confirm('Möchten Sie den Hersteller \'<?php echo addslashes($man['name']); ?>\' wirklich löschen?');"><i class="fas fa-trash"></i></a></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal für Asset Modelle -->
    <div class="modal-overlay" id="modelModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title" id="modelModalTitle">Modell anlegen</h3>
                <button class="close-btn" onclick="closeModelModal()">&times;</button>
            </div>
            <form method="POST" id="modelForm">
                <input type="hidden" name="action" id="modelAction" value="create_model">
                <input type="hidden" name="id" id="modelId" value="">

                <div class="form-group">
                    <label>Kürzel (2 Großbuchstaben, z.B. LP)</label>
                    <input type="text" name="kuerzel" id="fieldKuerzel" class="form-control" maxlength="2" pattern="[A-Za-z]{2}" required placeholder="z.B. LP" style="text-transform: uppercase;">
                </div>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="name" id="fieldName" class="form-control" required placeholder="z.B. MacBook Pro 14">
                </div>
                <div class="form-group">
                    <label>Hersteller</label>
                    <select name="manufacturer_id" id="fieldManufacturer" class="form-control">
                        <option value="">- Kein Hersteller -</option>
                        <?php foreach ($manufacturers as $man): ?>
                            <option value="<?php echo $man['id']; ?>"><?php echo htmlspecialchars($man['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Kategorie</label>
                    <select name="category_id" id="fieldCategory" class="form-control">
                        <option value="">- Keine Kategorie -</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Modellnummer</label>
                    <input type="text" name="model_number" id="fieldModelNumber" class="form-control" placeholder="z.B. A2442">
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Speichern</button>
                    <button type="button" class="btn" style="background: rgba(255,255,255,0.1); flex:1;" onclick="closeModelModal()">Abbrechen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lösch-Formular (versteckt) -->
    <form method="POST" id="deleteModelForm" style="display:none;">
        <input type="hidden" name="action" value="delete_model">
        <input type="hidden" name="id" id="deleteModelId">
    </form>

    <script>
        const modelModal = document.getElementById('modelModal');
        const modelForm = document.getElementById('modelForm');
        const modelModalTitle = document.getElementById('modelModalTitle');
        const modelActionInput = document.getElementById('modelAction');
        const modelIdInput = document.getElementById('modelId');
        
        const kuerzelInput = document.getElementById('fieldKuerzel');
        const nameInput = document.getElementById('fieldName');
        const manufacturerInput = document.getElementById('fieldManufacturer');
        const categoryInput = document.getElementById('fieldCategory');
        const modelNumberInput = document.getElementById('fieldModelNumber');

        function openCreateModelModal() {
            modelModalTitle.textContent = "Modell anlegen";
            modelActionInput.value = "create_model";
            modelIdInput.value = "";
            modelForm.reset();
            modelModal.classList.add('active');
        }

        function openEditModelModal(model) {
            modelModalTitle.textContent = "Modell bearbeiten";
            modelActionInput.value = "update_model";
            modelIdInput.value = model.id;
            
            kuerzelInput.value = model.kuerzel || '';
            nameInput.value = model.name;
            manufacturerInput.value = model.manufacturer_id || '';
            categoryInput.value = model.category_id || '';
            modelNumberInput.value = model.model_number || '';
            
            modelModal.classList.add('active');
        }

        function closeModelModal() {
            modelModal.classList.remove('active');
        }

        function confirmDeleteModel(id, name) {
            if (confirm("Möchten Sie das Modell '" + name + "' wirklich löschen?")) {
                document.getElementById('deleteModelId').value = id;
                document.getElementById('deleteModelForm').submit();
            }
        }

        // Automatisches Uppercase für Kürzel
        kuerzelInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    </script>
</body>
</html>
