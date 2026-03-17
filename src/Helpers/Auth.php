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
    }

    public static function logout() {
        self::startSession();
        session_destroy();
    }

    public static function isLoggedIn() {
        self::startSession();
        return isset($_SESSION['user_id']);
    }

    public static function getRole() {
        self::startSession();
        return $_SESSION['role'] ?? 'user';
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
}
