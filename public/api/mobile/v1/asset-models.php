<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_method('GET');
require_mobile_editor_auth();

$db = get_db();

$sql = 'SELECT id, name, category_id, manufacturer_id, model_number, kuerzel, has_sim_fields, has_hardware_fields, created_at FROM asset_models ORDER BY name ASC';
$stmt = $db->query($sql);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$meta = compute_collection_version($db, 'asset_models', 'created_at');

send_json([
    'success' => true,
    'version' => $meta['version'],
    'updated_at' => $meta['updated_at'],
    'items' => array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'category_id' => $row['category_id'] !== null ? (int)$row['category_id'] : null,
            'manufacturer_id' => $row['manufacturer_id'] !== null ? (int)$row['manufacturer_id'] : null,
            'model_number' => $row['model_number'] !== null ? (string)$row['model_number'] : null,
            'kuerzel' => $row['kuerzel'] !== null ? (string)$row['kuerzel'] : null,
            'has_sim_fields' => (int)$row['has_sim_fields'] === 1,
            'has_hardware_fields' => (int)$row['has_hardware_fields'] === 1,
        ];
    }, $items),
]);
