<?php
session_start();
require_once 'includes/config.php';

$name = $_SESSION['full_name'] ?? '';

// Сохраняем flash в cookie ДО уничтожения сессии
$flashMsg  = 'Вы вышли из системы' . ($name ? ', ' . $name : '') . '. До свидания!';
setcookie('_flash_msg',  $flashMsg, time() + 30, '/');
setcookie('_flash_type', 'info',    time() + 30, '/');

session_destroy();
redirect('index.php');
