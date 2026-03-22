<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

require_method('GET');
$user = require_mobile_editor_auth();

$db = get_db();

$companiesMeta = compute_collection_version($db, 'locations', 'created_at');
$modelsMeta = compute_collection_version($db, 'asset_models', 'created_at');

send_json([
    'success' => true,
    'server_time' => gmdate('c'),
    'authenticated_user' => $user,
    'resources' => [
        'companies' => [
            'endpoint' => '/api/mobile/v1/companies.php',
            'version' => $companiesMeta['version'],
            'updated_at' => $companiesMeta['updated_at'],
            'count' => $companiesMeta['count'],
        ],
        'asset_models' => [
            'endpoint' => '/api/mobile/v1/asset-models.php',
            'version' => $modelsMeta['version'],
            'updated_at' => $modelsMeta['updated_at'],
            'count' => $modelsMeta['count'],
        ],
    ],
]);
