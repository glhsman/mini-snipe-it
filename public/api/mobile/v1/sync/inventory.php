<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

require_method('POST');
require_mobile_editor_auth();

$db = get_db();
$input = read_json_body();
$items = $input['items'] ?? null;

if (!is_array($items)) {
    send_json([
        'success' => false,
        'error' => 'invalid_payload',
        'message' => 'items muss ein Array sein.',
    ], 422);
}

$db->exec(
    "CREATE TABLE IF NOT EXISTS inventory_staging (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        client_id VARCHAR(120) NOT NULL,
        serial_number VARCHAR(255) NOT NULL,
        asset_model_id INT NULL,
        room_text VARCHAR(255) NULL,
        comment_text TEXT NULL,
        company_id INT NULL,
        company_name VARCHAR(255) NULL,
        captured_at DATETIME NULL,
        sync_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
        review_note TEXT NULL,
        reviewed_at DATETIME NULL,
        reviewed_by_user_id INT NULL,
        target_asset_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_inventory_staging_client_id (client_id),
        INDEX idx_inventory_staging_status (sync_status),
        INDEX idx_inventory_staging_serial (serial_number),
        INDEX idx_inventory_staging_company (company_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
);

$db->exec('ALTER TABLE inventory_staging ADD COLUMN IF NOT EXISTS room_text VARCHAR(255) NULL');
$db->exec('ALTER TABLE inventory_staging ADD COLUMN IF NOT EXISTS comment_text TEXT NULL');
$db->exec('ALTER TABLE assets ADD COLUMN IF NOT EXISTS room VARCHAR(255) NULL');
$db->exec('ALTER TABLE assets ADD COLUMN IF NOT EXISTS last_inventur DATETIME NULL');

$insertSql = 'INSERT INTO inventory_staging
    (client_id, serial_number, asset_model_id, room_text, comment_text, company_id, company_name, captured_at, sync_status)
    VALUES (:client_id, :serial_number, :asset_model_id, :room_text, :comment_text, :company_id, :company_name, :captured_at, \'pending\')
    ON DUPLICATE KEY UPDATE
        serial_number = VALUES(serial_number),
        asset_model_id = VALUES(asset_model_id),
        room_text = VALUES(room_text),
        comment_text = VALUES(comment_text),
        company_id = VALUES(company_id),
        company_name = VALUES(company_name),
        captured_at = VALUES(captured_at),
        sync_status = \'pending\',
        updated_at = CURRENT_TIMESTAMP';

$stmt = $db->prepare($insertSql);

$updateAssetSql = 'UPDATE assets
    SET
        room = COALESCE(:room_text, room),
        last_inventur = COALESCE(:captured_at, last_inventur),
        updated_at = CURRENT_TIMESTAMP
    WHERE archiv_bit = 0
      AND (
          UPPER(COALESCE(serial, "")) = UPPER(:code_serial)
          OR UPPER(COALESCE(asset_tag, "")) = UPPER(:code_tag)
      )
    ORDER BY id DESC
    LIMIT 1';

$updateAssetStmt = $db->prepare($updateAssetSql);

$syncedCount = 0;
$updatedAssetsCount = 0;
$errors = [];

foreach ($items as $index => $item) {
    if (!is_array($item)) {
        $errors[] = [
            'index' => $index,
            'error' => 'item_not_object',
        ];
        continue;
    }

    $clientId = trim((string)($item['client_id'] ?? ''));
    $serialNumber = trim((string)($item['serial_number'] ?? ''));

    if ($clientId === '' || $serialNumber === '') {
        $errors[] = [
            'index' => $index,
            'error' => 'missing_client_or_serial',
        ];
        continue;
    }

    $timestamp = $item['timestamp'] ?? null;
    $capturedAt = null;
    if (is_string($timestamp) && trim($timestamp) !== '') {
        $dt = date_create($timestamp);
        if ($dt !== false) {
            $capturedAt = $dt->format('Y-m-d H:i:s');
        }
    }

    $stmt->execute([
        ':client_id' => $clientId,
        ':serial_number' => $serialNumber,
        ':asset_model_id' => isset($item['asset_model_id']) ? (int)$item['asset_model_id'] : null,
        ':room_text' => isset($item['location']) ? (string)$item['location'] : null,
        ':comment_text' => isset($item['comment']) ? (string)$item['comment'] : null,
        ':company_id' => isset($item['company_id']) ? (int)$item['company_id'] : null,
        ':company_name' => isset($item['company_name']) ? (string)$item['company_name'] : null,
        ':captured_at' => $capturedAt,
    ]);

    // Mirror key inventory information back into the live asset record.
    // The scanner field can contain serial or asset-tag, so we match both.
    $updateAssetStmt->execute([
        ':room_text' => isset($item['location']) ? (string)$item['location'] : null,
        ':captured_at' => $capturedAt,
        ':code_serial' => $serialNumber,
        ':code_tag' => $serialNumber,
    ]);

    if ($updateAssetStmt->rowCount() > 0) {
        $updatedAssetsCount++;
    }

    $syncedCount++;
}

send_json([
    'success' => true,
    'received' => count($items),
    'synced' => $syncedCount,
    'assets_updated' => $updatedAssetsCount,
    'errors' => $errors,
]);
