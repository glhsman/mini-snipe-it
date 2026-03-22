<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../config/db.php';
require_once __DIR__ . '/../../../../src/Helpers/Auth.php';
require_once __DIR__ . '/../../../../src/Controllers/UserController.php';

use App\Controllers\UserController;
use App\Helpers\Auth;

// Keep API responses machine-readable even if PHP emits notices/warnings.
if (ob_get_level() === 0) {
    ob_start();
}
@ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function send_json(array $payload, int $statusCode = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function require_method(string $expectedMethod): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== strtoupper($expectedMethod)) {
        send_json([
            'success' => false,
            'error' => 'method_not_allowed',
        ], 405);
    }
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        send_json([
            'success' => false,
            'error' => 'invalid_json',
        ], 400);
    }

    return $data;
}

function get_db(): PDO
{
    return Database::getInstance();
}

function require_mobile_editor_auth(): array
{
    Auth::startSession();

    if (!Auth::isLoggedIn()) {
        send_json([
            'success' => false,
            'error' => 'unauthenticated',
        ], 401);
    }

    if (!Auth::isEditor()) {
        send_json([
            'success' => false,
            'error' => 'forbidden',
        ], 403);
    }

    return [
        'user_id' => Auth::getUserId(),
        'username' => Auth::getUsername(),
        'role' => Auth::getRole(),
    ];
}

function compute_collection_version(PDO $db, string $table, string $updatedColumn = 'created_at'): array
{
    $sql = sprintf(
        'SELECT COUNT(*) AS total_count, MAX(%s) AS max_updated_at FROM %s',
        preg_replace('/[^a-zA-Z0-9_]/', '', $updatedColumn),
        preg_replace('/[^a-zA-Z0-9_]/', '', $table)
    );

    $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
    $count = (int)($row['total_count'] ?? 0);
    $maxUpdatedAt = $row['max_updated_at'] ?? null;
    $version = sha1($table . '|' . $count . '|' . ($maxUpdatedAt ?? 'null'));

    return [
        'version' => $version,
        'updated_at' => $maxUpdatedAt,
        'count' => $count,
    ];
}

function get_user_controller(PDO $db): UserController
{
    return new UserController($db);
}
