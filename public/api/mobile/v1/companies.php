<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

require_method('GET');
require_mobile_editor_auth();

$db = get_db();

$sql = 'SELECT id, name, address, city, kuerzel, created_at FROM locations ORDER BY name ASC';
$stmt = $db->query($sql);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$meta = compute_collection_version($db, 'locations', 'created_at');

send_json([
    'success' => true,
    'version' => $meta['version'],
    'updated_at' => $meta['updated_at'],
    'items' => array_map(static function (array $row): array {
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'address' => $row['address'] !== null ? (string)$row['address'] : null,
            'city' => $row['city'] !== null ? (string)$row['city'] : null,
            'kuerzel' => $row['kuerzel'] !== null ? (string)$row['kuerzel'] : null,
        ];
    }, $items),
]);
