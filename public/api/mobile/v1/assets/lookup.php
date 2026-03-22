<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

require_method('GET');
require_mobile_editor_auth();

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    send_json([
        'success' => false,
        'error' => 'missing_code',
        'message' => 'Parameter code ist erforderlich.',
    ], 422);
}

try {
    $db = get_db();

        $sql = 'SELECT
                a.id AS asset_id,
                a.asset_tag,
                a.serial,
                a.model_id,
                m.name AS model_name
            FROM assets a
            LEFT JOIN asset_models m ON m.id = a.model_id
                WHERE COALESCE(a.archiv_bit, 0) = 0
              AND (
                      UPPER(COALESCE(a.serial, \'\')) = UPPER(:code_serial)
                      OR UPPER(COALESCE(a.asset_tag, \'\')) = UPPER(:code_tag)
              )
            ORDER BY a.id DESC
            LIMIT 1';

    $stmt = $db->prepare($sql);
        $stmt->execute([
            ':code_serial' => $code,
            ':code_tag' => $code,
        ]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('mobile_lookup_failed: ' . $e->getMessage());
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $isLocalHost = stripos($host, 'localhost') !== false || stripos($host, '127.0.0.1') !== false;
    send_json([
        'success' => false,
        'error' => 'lookup_failed',
        'message' => 'Lookup aktuell nicht verfuegbar.',
        'detail' => $isLocalHost ? $e->getMessage() : null,
    ], 500);
}

if (!$row) {
    send_json([
        'success' => true,
        'found' => false,
        'model' => null,
    ]);
}

send_json([
    'success' => true,
    'found' => true,
    'asset' => [
        'id' => (int)$row['asset_id'],
        'serial' => $row['serial'] !== null ? (string)$row['serial'] : null,
        'asset_tag' => $row['asset_tag'] !== null ? (string)$row['asset_tag'] : null,
    ],
    'model' => [
        'id' => $row['model_id'] !== null ? (int)$row['model_id'] : null,
        'name' => $row['model_name'] !== null ? (string)$row['model_name'] : null,
    ],
]);
