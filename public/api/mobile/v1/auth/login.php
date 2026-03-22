<?php

declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use App\Helpers\Auth;

require_method('POST');

$db = get_db();
$userController = get_user_controller($db);
$input = read_json_body();

$username = trim((string)($input['username'] ?? ''));
$password = (string)($input['password'] ?? '');

if ($username === '' || $password === '') {
    send_json([
        'success' => false,
        'error' => 'missing_credentials',
        'message' => 'Benutzername und Passwort sind erforderlich.',
    ], 422);
}

$authResult = $userController->authenticateDetailed($username, $password);

if (!$authResult['success']) {
    if (($authResult['reason'] ?? null) === 'login_disabled') {
        Auth::logLoginBlocked($authResult['username'] ?: $username, 'login_disabled', $authResult['user_id']);
    } else {
        Auth::logLoginFailed($authResult['username'] ?: $username, (string)$authResult['reason'], $authResult['user_id']);
    }

    send_json([
        'success' => false,
        'error' => 'invalid_credentials',
        'message' => 'Ungültiger Benutzername oder Passwort.',
    ], 401);
}

$user = $authResult['user'];
$role = (string)($user['role'] ?? 'user');

if (!in_array($role, ['admin', 'editor'], true)) {
    send_json([
        'success' => false,
        'error' => 'forbidden',
        'message' => 'Keine Berechtigung für die mobile Inventur.',
    ], 403);
}

Auth::login($user);

send_json([
    'success' => true,
    'user' => [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'role' => $role,
        'display_name' => trim(((string)($user['first_name'] ?? '')) . ' ' . ((string)($user['last_name'] ?? ''))),
    ],
    'session' => [
        'type' => 'php_session',
        'expires_in_seconds' => 7200,
    ],
]);
