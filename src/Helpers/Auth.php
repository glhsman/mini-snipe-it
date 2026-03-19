<?php
namespace App\Helpers;

class Auth {
    public static function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function login($user) {
        self::startSession();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        self::logAction($user['id'], $user['username'], 'login');
    }

    public static function logout() {
        self::startSession();
        $userId   = $_SESSION['user_id']   ?? null;
        $username = $_SESSION['username']  ?? 'unbekannt';
        self::logAction($userId, $username, 'logout');
        session_destroy();
    }

    public static function logLoginFailed(string $username, string $reason = 'invalid_credentials', ?int $userId = null): void {
        self::logAction($userId, $username, 'login_failed', $reason);
    }

    public static function logLoginBlocked(string $username, string $reason = 'login_not_allowed', ?int $userId = null): void {
        self::logAction($userId, $username, 'login_blocked', $reason);
    }

    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user_id']);
    }

    public static function getRole() {
        self::startSession();
        return $_SESSION['role'] ?? 'user';
    }

    public static function getUsername() {
        self::startSession();
        return $_SESSION['username'] ?? 'Gast';
    }

    public static function getUserId() {
        self::startSession();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function isAdmin() {
        return self::getRole() === 'admin';
    }

    public static function isEditor() {
        $role = self::getRole();
        return $role === 'admin' || $role === 'editor';
    }

    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            die("Zugriff verweigert: Administrator-Rechte erforderlich.");
        }
    }

    public static function requireEditor() {
        self::requireLogin();
        if (!self::isEditor()) {
            die("Zugriff verweigert: Bearbeiter-Rechte erforderlich.");
        }
    }

    private static function logAction(?int $userId, string $username, string $action, ?string $reason = null): void {
        try {
            require_once __DIR__ . '/../../config/db.php';
            $db = \Database::getInstance();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = isset($_SERVER['HTTP_USER_AGENT'])
                    ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
                    : null;
            $safeReason = $reason !== null ? substr($reason, 0, 100) : null;
            $stmt = $db->prepare(
                "INSERT INTO login_logs (user_id, username, action, reason, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$userId, $username, $action, $safeReason, $ip, $ua]);
        } catch (\Throwable $e) {
            // Logging-Fehler dürfen den Login-Prozess nicht unterbrechen
        }
    }
}
