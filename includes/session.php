<?php
// Set session configurations BEFORE session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Strict');

// Now start the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connection.php';

class Session {
    
    public static function start() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }
    
    public static function get($key) {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }
    
    public static function delete($key) {
        unset($_SESSION[$key]);
    }
    
    public static function destroy() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['user_type']);
    }
    
    public static function isAdmin() {
        return self::isLoggedIn() && $_SESSION['user_type'] == 'admin';
    }
    
    public static function isUser() {
        return self::isLoggedIn() && $_SESSION['user_type'] == 'user';
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . SITE_URL . '/index.php');
            exit();
        }
    }
    
    public static function requireAdmin() {
        self::requireLogin();
        if (!self::isAdmin()) {
            header('Location: ' . SITE_URL . '/user/dashboard.php');
            exit();
        }
    }
    
    public static function requireUser() {
        self::requireLogin();
        if (!self::isUser()) {
            header('Location: ' . SITE_URL . '/admin/dashboard.php');
            exit();
        }
    }
    
    // Regenerate session ID for security
    public static function regenerate() {
        session_regenerate_id(true);
    }
}

// Initialize session
Session::start();
?>