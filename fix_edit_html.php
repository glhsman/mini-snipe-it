<?php
$file = __DIR__ . '/public/asset_edit.php';
$content = file_get_contents($file);

// 1. RAM ersetzen
$patternRam = '/<div class="form-group">\s*<label>RAM \(GB\)<\/label>\s*<input type="number" name="ram".*?>\s*<\/div>/s';
$replaceRam = '<div class="form-group">
                        <label>RAM</label>
                        <select name="ram" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($ramOptions as $opt): ?>
                                <option value="<?php echo $opt[\'id\']; ?>" <?php echo (($asset[\'ram\'] ?? \'\') == $opt[\'id\']) ? \'selected\' : \'\'; ?>>
                                    <?php echo htmlspecialchars($opt[\'value\']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>';

$content = preg_replace($patternRam, $replaceRam, $content);

// 2. SSD ersetzen
$patternSsd = '/<div class="form-group">\s*<label>SSD-Größe \(GB\)<\/label>\s*<input type="number" name="ssd_size".*?>\s*<\/div>/s';
$replaceSsd = '<div class="form-group">
                        <label>SSD-Größe</label>
                        <select name="ssd_size" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($ssdOptions as $opt): ?>
                                <option value="<?php echo $opt[\'id\']; ?>" <?php echo (($asset[\'ssd_size\'] ?? \'\') == $opt[\'id\']) ? \'selected\' : \'\'; ?>>
                                    <?php echo htmlspecialchars($opt[\'value\']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>';

$content = preg_replace($patternSsd, $replaceSsd, $content);

// 3. Cores ersetzen
$patternCores = '/<div class="form-group">\s*<label>Cores \(Kerne\)<\/label>\s*<input type="number" name="cores".*?>\s*<\/div>/s';
$replaceCores = '<div class="form-group">
                        <label>Cores (Kerne)</label>
                        <select name="cores" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($coresOptions as $opt): ?>
                                <option value="<?php echo $opt[\'id\']; ?>" <?php echo (($asset[\'cores\'] ?? \'\') == $opt[\'id\']) ? \'selected\' : \'\'; ?>>
                                    <?php echo htmlspecialchars($opt[\'value\']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>';

$content = preg_replace($patternCores, $replaceCores, $content);

// 4. OS ersetzen
$patternOs = '/<div class="form-group" style="grid-column: 1 \/ -1;">\s*<label>OS \/ Betriebssystem<\/label>\s*<input type="text" name="os_version".*?>\s*<\/div>/s';
$replaceOs = '<div class="form-group" style="grid-column: 1 / -1; width: 50%;">
                        <label>OS / Betriebssystem</label>
                        <select name="os_version" class="form-control">
                            <option value="">- Wählen -</option>
                            <?php foreach ($osOptions as $opt): ?>
                                <option value="<?php echo $opt[\'id\']; ?>" <?php echo (($asset[\'os_version\'] ?? \'\') == $opt[\'id\']) ? \'selected\' : \'\'; ?>>
                                    <?php echo htmlspecialchars($opt[\'value\']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>';

$content = preg_replace($patternOs, $replaceOs, $content);

file_put_contents($file, $content);
echo "HTML updated successfully.\n";
