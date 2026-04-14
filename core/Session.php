<?php

// Auth & state, i.e: How users stay logged in

class Session {
    public static function start() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public static function get($key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }

    public static function has($key) {
        return isset($_SESSION[$key]);
    }

    public static function remove($key) {
        unset($_SESSION[$key]);
    }

    public static function destroy() {
        session_destroy();
        $_SESSION = [];
    }

    public static function isLoggedIn() {
        return self::has('user_id');
    }

    public static function getUserId() {
        return self::get('user_id');
    }

    // Flash messages
    public static function setFlash($type, $message) {
        $_SESSION['flash'][$type] = $message;
    }

    public static function getFlash($type) {
        $message = $_SESSION['flash'][$type] ?? null;
        unset($_SESSION['flash'][$type]);
        return $message;
    }
}