<?php

/**
 * Minecraft 服务器封禁公示系统
 *
 * @copyright  2026 balareshe (摆烂人生)
 * @link       https://blog.umrc.cn
 * @license    MIT License
 */



if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


function requireAdminLogin() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

        
        header('Location: /admin/index.php');
        exit;
    }
}


function checkAdminPermission($requiredLevel = 1) {
    requireAdminLogin();

    
    $userLevel = $_SESSION['admin_level'] ?? 1;

    if ($userLevel < $requiredLevel) {
        
        http_response_code(403);
        include __DIR__ . '/../admin/403.php';
        exit;
    }

    return true;
}


function logAdminAction($action, $details = '') {
    if (!isset($_SESSION['admin_username'])) {
        return;
    }

    $logFile = __DIR__ . '/../logs/admin_actions.log';
    $logDir = dirname($logFile);

    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $username = $_SESSION['admin_username'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $logEntry = sprintf(
        "[%s] %s - %s - %s - %s\n",
        $timestamp,
        $ip,
        $username,
        $action,
        $details
    );

    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}


function validateCsrfToken() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }

    $token = $_POST['csrf_token'] ?? '';
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {

        http_response_code(403);
        die('CSRF 令牌无效或已过期。');
    }


    return true;
}


function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


function adminLogout() {
    
    if (isset($_SESSION['admin_username'])) {
        logAdminAction('LOGOUT', '管理员退出登录');
    }

    
    $_SESSION = array();

    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    
    session_destroy();

    
    header('Location: index.php');
    exit;
}


function checkBruteForce($username, $maxAttempts = 5, $lockoutTime = 900) { 
    $lockFile = __DIR__ . '/../logs/login_attempts.log';
    $logDir = dirname($lockFile);

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $attempts = [];
    if (file_exists($lockFile)) {
        $attempts = json_decode(file_get_contents($lockFile), true) ?: [];
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = md5($ip . $username);

    $now = time();
    $userAttempts = $attempts[$key] ?? [];

    
    $userAttempts = array_filter($userAttempts, function($timestamp) use ($now, $lockoutTime) {
        return ($now - $timestamp) < $lockoutTime;
    });

    
    if (count($userAttempts) >= $maxAttempts) {
        return false; 
    }

    
    $userAttempts[] = $now;
    $attempts[$key] = $userAttempts;

    file_put_contents($lockFile, json_encode($attempts), LOCK_EX);
    return true;
}


function clearBruteForceRecord($username) {
    $lockFile = __DIR__ . '/../logs/login_attempts.log';
    if (!file_exists($lockFile)) {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = md5($ip . $username);

    $attempts = json_decode(file_get_contents($lockFile), true) ?: [];
    unset($attempts[$key]);

    file_put_contents($lockFile, json_encode($attempts), LOCK_EX);
}