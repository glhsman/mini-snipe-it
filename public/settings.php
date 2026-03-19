<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../src/Controllers/MasterDataController.php';
require_once __DIR__ . '/../src/Helpers/Auth.php';

use App\Controllers\MasterDataController;
use App\Helpers\Auth;

Auth::requireAdmin();

$db = Database::getInstance();
// Auto-Migration für Hardware-Lookups
$stmt = $db->query("SHOW TABLES LIKE 'lookup_ram'");
if ($stmt->rowCount() == 0) {
    require_once __DIR__ . '/migrate_lookups.php';
    runLookupMigration($db);
}

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
$ramOptions = $masterData->getLookupOptions('ram');
$ssdOptions = $masterData->getLookupOptions('ssd');
$coresOptions = $masterData->getLookupOptions('cores');
$osOptions = $masterData->getLookupOptions('os');

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Einstellungen - Mini-Snipe</title>
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime(__DIR__ . '/assets/css/style.css'); ?>">
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
<body class="<?php echo ($_COOKIE['theme'] ?? 'dark') === 'light' ? 'light-mode' : ''; ?>">
    <?php include_once __DIR__ . '/includes/top_navbar.php'; ?>
    <div class="sidebar">
        <div class="logo">Mini-Snipe</div>
        <nav>
            <a href="index.php" class="nav-link"><i class="fas fa-home"></i> Dashboard</a>
            <a href="assets.php" class="nav-link"><i class="fas fa-laptop"></i> Assets</a>
            <a href="users.php" class="nav-link"><i class="fas fa-users"></i> User</a>
            <a href="locations.php" class="nav-link"><i class="fas fa-map-marker-alt"></i> Standorte</a>
            <a href="settings.php" class="nav-link active"><i class="fas fa-cog"></i> Verwaltung</a>
                <a href="settings_general.php" class="nav-link"><i class="fas fa-sliders-h"></i> Einstellungen</a>
        </nav>
    </div>

    <main class="main-content">
        <header class="header">
            <h1>Stammdatenverwaltung</h1>
        <div style="display: flex; gap: 0.5rem; margin-top: 1rem; margin-bottom: 2rem;">
            <a href="#models" class="btn btn-sm" style="background: rgba(255,255,255,0.05);">Modelle</a>
            <a href="#categories" class="btn btn-sm" style="background: rgba(255,255,255,0.05);">Kategorien</a>
            <a href="#manufacturers" class="btn btn-sm" style="background: rgba(255,255,255,0.05);">Hersteller</a>
            <a href="#hardware-options" class="btn btn-sm" style="background: var(--primary-color); color: white;">Hardware-Optionen</a>
        </div>

        </header>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="settings-section" id="models">
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
            <div class="settings-section" id="categories">
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

            <div class="settings-section" id="manufacturers">
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

                <div class="settings-section" id="hardware-options" style="margin-top: 3rem;">
            <div class="settings-header">
                <h2>Hardware & OS Optionen</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                
                <!-- RAM -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3>RAM</h3>
                        <button class="btn btn-sm btn-primary" onclick="openLookupModal('ram')"><i class="fas fa-plus"></i></button>
                    </div>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($ramOptions as $opt): ?>
                            <li style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <span><?php echo htmlspecialchars($opt['value']); ?></span>
                                <button onclick="deleteLookup('ram', <?php echo $opt['id']; ?>)" style="background:none; border:none; cursor:pointer; color: var(--accent-rose);"><i class="fas fa-trash"></i></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- SSD -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3>SSD</h3>
                        <button class="btn btn-sm btn-primary" onclick="openLookupModal('ssd')"><i class="fas fa-plus"></i></button>
                    </div>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($ssdOptions as $opt): ?>
                            <li style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <span><?php echo htmlspecialchars($opt['value']); ?></span>
                                <button onclick="deleteLookup('ssd', <?php echo $opt['id']; ?>)" style="background:none; border:none; cursor:pointer; color: var(--accent-rose);"><i class="fas fa-trash"></i></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Cores -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3>Cores (Kerne)</h3>
                        <button class="btn btn-sm btn-primary" onclick="openLookupModal('cores')"><i class="fas fa-plus"></i></button>
                    </div>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($coresOptions as $opt): ?>
                            <li style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <span><?php echo htmlspecialchars($opt['value']); ?></span>
                                <button onclick="deleteLookup('cores', <?php echo $opt['id']; ?>)" style="background:none; border:none; cursor:pointer; color: var(--accent-rose);"><i class="fas fa-trash"></i></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- OS -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3>OS / Betriebssystem</h3>
                        <button class="btn btn-sm btn-primary" onclick="openLookupModal('os')"><i class="fas fa-plus"></i></button>
                    </div>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($osOptions as $opt): ?>
                            <li style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <span><?php echo htmlspecialchars($opt['value']); ?></span>
                                <button onclick="deleteLookup('os', <?php echo $opt['id']; ?>)" style="background:none; border:none; cursor:pointer; color: var(--accent-rose);"><i class="fas fa-trash"></i></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

<div class="settings-section" style="margin-top: 3rem;">
            <div class="settings-header">
                <h2>Datenimport (CSV)</h2>
            </div>

            <!-- Import Order Hint -->
            <div class="alert" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); margin-bottom: 1.5rem; display: flex; align-items: flex-start; gap: 0.75rem; padding: 1.25rem;">
                <i class="fas fa-info-circle" style="font-size: 1.25rem; margin-top: 0.15rem;"></i>
                <div>
                    <strong style="display: block; margin-bottom: 0.25rem;">Empfohlene Import-Reihenfolge bei neuer Datenbank:</strong>
                    <p style="font-size: 0.875rem; color: var(--text-main); margin-bottom: 0.5rem;">
                        1. <strong>Standorte</strong> &rarr; 2. <strong>Benutzer</strong> &rarr; 3. <strong>Assets</strong>
                    </p>
                    <p style="font-size: 0.825rem; color: var(--text-muted); line-height: 1.4;">
                        Da Benutzer und Assets auf Standorte verweisen (und Assets auf Benutzer), sollten diese Grunddaten zuerst existieren.<br>
                        <em>Hinweis:</em> Hersteller, Kategorien und Modelle werden beim Asset-Import automatisch erstellt, falls sie fehlen.
                    </p>
                </div>
            </div>

            <div class="card" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 2rem; padding: 2rem;">
                <div>
                    <h3 style="color: var(--text-main); margin-bottom: 1rem;"><i class="fas fa-map-marker-alt" style="margin-right: 0.5rem; color: #06b6d4;"></i> Standorte Importieren</h3>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">Lade deine Standorte hoch. Dies sollte vor dem Benutzer- und Asset-Import erfolgen.<br><code style="font-size: 0.75rem; color: #06b6d4; background: rgba(6, 182, 212, 0.1); padding: 0.2rem 0.4rem; border-radius: 0.25rem; display: inline-block; margin-top: 0.5rem;">Spalten: name, address, city, kuerzel</code></p>
                    <div style="display: flex; gap: 1rem;">
                        <a href="import_locations.php" class="btn btn-primary" style="background: #06b6d4;"><i class="fas fa-upload"></i> Importieren</a>
                        <a href="downloads/sample_locations.csv" download class="btn" style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border);"><i class="fas fa-download"></i> Muster CSV</a>
                    </div>
                </div>
                <div style="border-left: 1px solid var(--glass-border); padding-left: 2rem;">
                    <h3 style="color: var(--text-main); margin-bottom: 1rem;"><i class="fas fa-users" style="margin-right: 0.5rem; color: var(--primary-color);"></i> Benutzer Importieren</h3>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">Erstelle mehrere Benutzerkonten auf einmal. Du kannst Daten für Standorte automatisch zuordnen lassen.<br><code style="font-size: 0.75rem; color: var(--primary-color); background: rgba(99, 102, 241, 0.1); padding: 0.2rem 0.4rem; border-radius: 0.25rem; display: inline-block; margin-top: 0.5rem;">Spalten: username, email, first_name, last_name, location_name</code></p>
                    <div style="display: flex; gap: 1rem;">
                        <a href="import_users.php" class="btn btn-primary"><i class="fas fa-upload"></i> Importieren</a>
                        <a href="downloads/sample_users.csv" download class="btn" style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border);"><i class="fas fa-download"></i> Muster CSV</a>
                    </div>
                </div>
                <div style="border-left: 1px solid var(--glass-border); padding-left: 2rem;">
                    <h3 style="color: var(--text-main); margin-bottom: 1rem;"><i class="fas fa-laptop" style="margin-right: 0.5rem; color: #a855f7;"></i> Assets Importieren</h3>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">Lade deine Asset-Liste hoch. Fehlende Modelle, Hersteller und Kategorien können automatisch erstellt werden.<br><code style="font-size: 0.75rem; color: #a855f7; background: rgba(168, 85, 247, 0.1); padding: 0.2rem 0.4rem; border-radius: 0.25rem; display: inline-block; margin-top: 0.5rem;">Spalten: asset_tag, name, serial, model_name, ...</code></p>
                    <div style="display: flex; gap: 1rem;">
                        <a href="import_assets.php" class="btn btn-primary" style="background: #a855f7;"><i class="fas fa-upload"></i> Importieren</a>
                        <a href="downloads/sample_assets.csv" download class="btn" style="background: rgba(255,255,255,0.05); border: 1px solid var(--glass-border);"><i class="fas fa-download"></i> Muster CSV</a>
                    </div>
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
        if (kuerzelInput) {
            kuerzelInput.addEventListener('input', function() {
                this.value = this.value.toUpperCase();
            });
        }

        // Tabellensortierung für Modelle (Client-Side)
        document.querySelectorAll('.settings-section#models table.data-table th').forEach((th, index) => {
            if ([0, 1, 2].includes(index)) { // Nur Name, Hersteller, Kategorie
                th.style.cursor = 'pointer';
                const text = th.textContent;
                th.innerHTML = `<span>${text}</span> <i class="fas fa-sort" style="font-size: 0.75rem; color: rgba(255,255,255,0.3); margin-left: 5px;"></i>`;

                th.addEventListener('click', () => {
                    const table = th.closest('table');
                    const tbody = table.querySelector('tbody');
                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    
                    if (rows.length === 1 && rows[0].innerText.includes("Keine Modelle")) return;

                    const isAsc = th.classList.toggle('asc');

                    // Reset alle anderen Icons in dieser Tabellen-Header
                    th.parentNode.querySelectorAll('i').forEach(i => {
                        i.className = 'fas fa-sort';
                        i.style.color = 'rgba(255,255,255,0.3)';
                    });

                    const icon = th.querySelector('i');
                    icon.className = isAsc ? 'fas fa-sort-up' : 'fas fa-sort-down';
                    icon.style.color = 'var(--primary-color)';

                    rows.sort((a, b) => {
                        let textA = a.children[index].textContent.trim().toLowerCase();
                        let textB = b.children[index].textContent.trim().toLowerCase();
                        return isAsc ? textA.localeCompare(textB, 'de') : textB.localeCompare(textA, 'de');
                    });

                    tbody.innerHTML = '';
                    rows.forEach(r => tbody.appendChild(r));
                });
            }
        });
    </script>
    <!-- Modal für Hardware-Lookups -->
    <div class="modal-overlay" id="lookupModal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title" id="lookupModalTitle">Eintrag hinzufügen</h3>
                <button class="close-btn" onclick="closeLookupModal()">&times;</button>
            </div>
            <form action="lookup_action.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="type" id="lookupType" value="">

                <div class="form-group">
                    <label id="lookupLabel">Wert</label>
                    <input type="text" name="value" class="form-control" required placeholder="z.B. 16 GB oder Windows 11">
                </div>

                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">Speichern</button>
                    <button type="button" class="btn" style="background: rgba(255,255,255,0.1); flex:1;" onclick="closeLookupModal()">Abbrechen</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lösch-Formular für Lookups (versteckt) -->
    <form action="lookup_action.php" method="POST" id="deleteLookupForm" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="type" id="deleteLookupType">
        <input type="hidden" name="id" id="deleteLookupId">
    </form>

    <script>
        function openLookupModal(type) {
            document.getElementById('lookupType').value = type;
            let title = "Eintrag hinzufügen";
            let label = "Wert";
            if (type === 'ram') { title = "RAM hinzufügen"; label = "RAM (z.B. 16 GB)"; }
            else if (type === 'ssd') { title = "SSD hinzufügen"; label = "Format: z.B. 512 GB oder 1 TB"; }
            else if (type === 'cores') { title = "Cores hinzufügen"; label = "Anzahl (z.B. 8)"; }
            else if (type === 'os') { title = "Betriebssystem hinzufügen"; label = "Name (z.B. Windows 11)"; }
            
            document.getElementById('lookupModalTitle').textContent = title;
            document.getElementById('lookupLabel').textContent = label;
            document.getElementById('lookupModal').classList.add('active');
        }

        function closeLookupModal() {
            document.getElementById('lookupModal').classList.remove('active');
        }

        function deleteLookup(type, id) {
            if (confirm("Möchten Sie diesen Eintrag wirklich löschen?")) {
                document.getElementById('deleteLookupType').value = type;
                document.getElementById('deleteLookupId').value = id;
                document.getElementById('deleteLookupForm').submit();
            }
        }
    </script>
</body>
</html>
