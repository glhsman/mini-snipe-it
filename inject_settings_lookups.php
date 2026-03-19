<?php
$file = __DIR__ . '/public/settings.php';
$content = file_get_contents($file);

// 1. Optionen laden am Anfang (nach den bestehenden Variablen)
$searchOptions = '$models = $masterData->getAssetModels();';
$insertOptions = "\n\$ramOptions = \$masterData->getLookupOptions('ram');\n"
               . "\$ssdOptions = \$masterData->getLookupOptions('ssd');\n"
               . "\$coresOptions = \$masterData->getLookupOptions('cores');\n"
               . "\$osOptions = \$masterData->getLookupOptions('os');\n";

if (strpos($content, $searchOptions) !== false && strpos($content, 'getLookupOptions') === false) {
    $content = str_replace($searchOptions, $searchOptions . $insertOptions, $content);
}

// 2. HTML Sektion vor Datenimport einfügen
$searchSection = '<div class="settings-section" style="margin-top: 3rem;">'; // Start des Datenimports
$insertSection = '        <div class="settings-section" id="hardware-options" style="margin-top: 3rem;">
            <div class="settings-header">
                <h2>Hardware & OS Optionen</h2>
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem;">
                
                <!-- RAM -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3>RAM</h3>
                        <button class="btn btn-sm btn-primary" onclick="openLookupModal(\'ram\')"><i class="fas fa-plus"></i></button>
                    </div>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($ramOptions as $opt): ?>
                            <li style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <span><?php echo htmlspecialchars($opt[\'value\']); ?></span>
                                <button onclick="deleteLookup(\'ram\', <?php echo $opt[\'id\']; ?>)" style="background:none; border:none; cursor:pointer; color: var(--accent-rose);"><i class="fas fa-trash"></i></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- SSD -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3>SSD</h3>
                        <button class="btn btn-sm btn-primary" onclick="openLookupModal(\'ssd\')"><i class="fas fa-plus"></i></button>
                    </div>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($ssdOptions as $opt): ?>
                            <li style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <span><?php echo htmlspecialchars($opt[\'value\']); ?></span>
                                <button onclick="deleteLookup(\'ssd\', <?php echo $opt[\'id\']; ?>)" style="background:none; border:none; cursor:pointer; color: var(--accent-rose);"><i class="fas fa-trash"></i></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Cores -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3>Cores (Kerne)</h3>
                        <button class="btn btn-sm btn-primary" onclick="openLookupModal(\'cores\')"><i class="fas fa-plus"></i></button>
                    </div>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($coresOptions as $opt): ?>
                            <li style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <span><?php echo htmlspecialchars($opt[\'value\']); ?></span>
                                <button onclick="deleteLookup(\'cores\', <?php echo $opt[\'id\']; ?>)" style="background:none; border:none; cursor:pointer; color: var(--accent-rose);"><i class="fas fa-trash"></i></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- OS -->
                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                        <h3>OS / Betriebssystem</h3>
                        <button class="btn btn-sm btn-primary" onclick="openLookupModal(\'os\')"><i class="fas fa-plus"></i></button>
                    </div>
                    <ul style="list-style: none; padding: 0;">
                        <?php foreach ($osOptions as $opt): ?>
                            <li style="display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <span><?php echo htmlspecialchars($opt[\'value\']); ?></span>
                                <button onclick="deleteLookup(\'os\', <?php echo $opt[\'id\']; ?>)" style="background:none; border:none; cursor:pointer; color: var(--accent-rose);"><i class="fas fa-trash"></i></button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

';

if (strpos($content, $searchSection) !== false && strpos($content, 'id="hardware-options"') === false) {
    $content = str_replace($searchSection, $insertSection . $searchSection, $content);
}

// 3. Modals und Scripts am Ende (vor </body>)
$searchEnd = '</body>';
$insertEnd = '    <!-- Modal für Hardware-Lookups -->
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
            document.getElementById(\'lookupType\').value = type;
            let title = "Eintrag hinzufügen";
            let label = "Wert";
            if (type === \'ram\') { title = "RAM hinzufügen"; label = "RAM (z.B. 16 GB)"; }
            else if (type === \'ssd\') { title = "SSD hinzufügen"; label = "Format: z.B. 512 GB oder 1 TB"; }
            else if (type === \'cores\') { title = "Cores hinzufügen"; label = "Anzahl (z.B. 8)"; }
            else if (type === \'os\') { title = "Betriebssystem hinzufügen"; label = "Name (z.B. Windows 11)"; }
            
            document.getElementById(\'lookupModalTitle\').textContent = title;
            document.getElementById(\'lookupLabel\').textContent = label;
            document.getElementById(\'lookupModal\').classList.add(\'active\');
        }

        function closeLookupModal() {
            document.getElementById(\'lookupModal\').classList.remove(\'active\');
        }

        function deleteLookup(type, id) {
            if (confirm("Möchten Sie diesen Eintrag wirklich löschen?")) {
                document.getElementById(\'deleteLookupType\').value = type;
                document.getElementById(\'deleteLookupId\').value = id;
                document.getElementById(\'deleteLookupForm\').submit();
            }
        }
    </script>
</body>';

if (strpos($content, 'id="lookupModal"') === false) {
    $content = str_replace($searchEnd, $insertEnd, $content);
}

file_put_contents($file, $content);
echo "settings.php updated with lookup sections.\n";
