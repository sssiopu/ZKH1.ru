<?php
/**
 * Конфигурация базы данных
 * Для OpenServer
 */

// Настройки подключения к БД
define('DB_HOST', 'localhost');
define('DB_NAME', 'zhkh_system');
define('DB_USER', 'root');  // Для OpenServer по умолчанию root
define('DB_PASS', '');      // Для OpenServer пароль обычно пустой
define('DB_CHARSET', 'utf8mb4');

// Настройки сайта
define('SITE_NAME', 'ДомУчет - Система управления ЖКХ');
define('SITE_URL', 'http://localhost/zhkh.diploma/');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// Динамический URL — работает на любом домене, не нужно менять вручную
$_protocol = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") ? "https" : "http";
$_host     = $_SERVER["HTTP_HOST"] ?? "localhost";
$_base     = rtrim(dirname(dirname($_SERVER["SCRIPT_NAME"])), "/");
define("UPLOAD_URL", $_protocol . "://" . $_host . $_base . "/uploads/");

// Настройки сессии
define('SESSION_LIFETIME', 3600 * 24); // 24 часа

// Класс для работы с БД
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Запрет клонирования
    private function __clone() {}
    
    // Запрет десериализации
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Вспомогательные функции
function db() {
    return Database::getInstance()->getConnection();
}

function escape($string) {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['role_name'] ?? '', $roles);
}

function requireRole($roles) {
    if (!hasRole($roles)) {
        die("Доступ запрещен");
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $stmt = db()->prepare("SELECT u.*, r.role_name FROM users u 
                           JOIN roles r ON u.role_id = r.role_id 
                           WHERE u.user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function uploadFile($file, $directory) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'error' => 'Недопустимый тип файла'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Файл слишком большой'];
    }
    
    $uploadDir = UPLOAD_PATH . $directory . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true, 
            'filename' => $filename,
            'path' => $directory . '/' . $filename,
            'url' => UPLOAD_URL . $directory . '/' . $filename
        ];
    }
    
    return ['success' => false, 'error' => 'Ошибка загрузки файла'];
}

function formatDate($date, $format = 'd.m.Y') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

function formatMoney($amount) {
    return number_format($amount, 2, '.', ' ') . ' ₽';
}

function sendNotification($userId, $title, $message, $type = 'info', $linkUrl = null) {
    $stmt = db()->prepare("INSERT INTO notifications (user_id, title, message, type, link_url) 
                           VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$userId, $title, $message, $type, $linkUrl]);
}

function getUnreadNotificationsCount($userId) {
    $stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}


// ---- Flash-сообщения (toast) ----
function setFlash(string $msg, string $type = 'info'): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['_flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $f ?? [];
}

// Читаем flash из cookie ДО любого вывода (вызывать в начале каждой страницы)
function initFlashFromCookie(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!empty($_COOKIE['_flash_msg'])) {
        $_SESSION['_flash_cookie'] = [
            'msg'  => $_COOKIE['_flash_msg'],
            'type' => $_COOKIE['_flash_type'] ?? 'info',
        ];
        setcookie('_flash_msg',  '', time() - 3600, '/');
        setcookie('_flash_type', '', time() - 3600, '/');
    }
}
