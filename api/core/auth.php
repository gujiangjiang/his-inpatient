<?php
// api/core/auth.php - 登录/角色校验/令牌回退
class Auth {
    private static $currentUser = null;

    public static function init() {
        // 检测是否已有会话ID通过 Cookie
        $hasCookie = isset($_COOKIE[session_name()]) || isset($_COOKIE['HIS_SESSION']);
        $sessionIdHeader = self::checkSessionIdHeader() ? $_SERVER['HTTP_X_SESSION_ID'] : null;

        if ($hasCookie && !$sessionIdHeader) {
            // 标准 Cookie 方式
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        } elseif (!$hasCookie && $sessionIdHeader) {
            // 令牌回退方式
            if (session_status() !== PHP_SESSION_NONE) {
                session_write_close();
            }
            session_id($sessionIdHeader);
            session_start();
        } else {
            // 两种都没有
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }

        if (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
            self::$currentUser = self::getUserById($_SESSION['user_id']);
        }
    }

    private static function checkSessionIdHeader() {
        return isset($_SERVER['HTTP_X_SESSION_ID']) && StringHelper::checkSessionId($_SERVER['HTTP_X_SESSION_ID']);
    }

    public static function login($username, $password) {
        $user = DB::selectOne('SELECT * FROM users WHERE username = ? LIMIT 1', [$username]);
        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        if ($user['is_active'] != 1) {
            return false;
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        self::$currentUser = $user;
        return $user;
    }

    public static function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
    }

    public static function user() {
        if (self::$currentUser === null) {
            self::init();
        }
        return self::$currentUser;
    }

    public static function isLoggedIn() {
        return self::user() !== null;
    }

    public static function getSessionId() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return session_id();
    }

    public static function requireRole($roles) {
        self::init();
        if (!self::isLoggedIn()) {
            Response::error('未授权', 401);
        }
        $user = self::user();
        if (!in_array($user['role'], $roles)) {
            Response::error('权限不足', 403);
        }
    }

    private static function getUserById($id) {
        return DB::selectOne('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
    }
}
