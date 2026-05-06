<?php
session_start();
require_once 'includes/config.php';

if (isLoggedIn()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        $stmt = db()->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id=r.role_id WHERE u.email=? AND u.is_active=1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['role_id']   = $user['role_id'];
            $_SESSION['role_name'] = $user['role_name'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email']     = $user['email'];
            $stmt = db()->prepare("UPDATE users SET last_login=NOW() WHERE user_id=?");
            $stmt->execute([$user['user_id']]);
            setFlash('Добро пожаловать, ' . $user['full_name'] . '!', 'success');
            redirect('index.php');
        } else {
            $error = 'Неверный email или пароль';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход — ДомУчет</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=PT+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/toast.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        :root{--blue:#2C6DB5;--blue-dark:#1a4f8a;--gray-100:#F5F7FA;--gray-200:#E8ECF2;--gray-400:#9DAABF;--gray-600:#5A6880;--gray-900:#1A2540;--white:#FFFFFF;}
        body{font-family:'PT Sans',sans-serif;background:linear-gradient(160deg,var(--blue) 0%,var(--blue-dark) 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;}
        .login-wrap{width:100%;max-width:420px;}
        .login-logo{text-align:center;margin-bottom:2rem;color:var(--white);}
        .login-logo-icon{width:60px;height:60px;background:rgba(255,255,255,.18);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:1.6rem;margin-bottom:.75rem;}
        .login-logo h1{font-size:1.6rem;font-weight:700;}
        .login-logo p{opacity:.75;font-size:.9rem;margin-top:.25rem;}
        .login-card{background:var(--white);border-radius:16px;padding:2rem;box-shadow:0 24px 64px rgba(0,0,0,.2);}
        .form-group{display:flex;flex-direction:column;gap:.35rem;margin-bottom:1rem;}
        .form-group label{font-size:.85rem;font-weight:700;color:var(--gray-600);display:flex;align-items:center;gap:.35rem;}
        .form-group input{border:1px solid var(--gray-200);border-radius:8px;padding:.75rem 1rem;font-size:.97rem;font-family:inherit;color:var(--gray-900);outline:none;transition:border-color .15s;}
        .form-group input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(44,109,181,.1);}
        .btn-login{width:100%;background:var(--blue);color:var(--white);border:none;border-radius:8px;padding:.85rem;font-size:1rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;display:flex;align-items:center;justify-content:center;gap:.5rem;margin-top:.5rem;}
        .btn-login:hover{background:var(--blue-dark);}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:8px;padding:.75rem 1rem;font-size:.9rem;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;}

        .back-link{display:block;text-align:center;margin-top:1.25rem;color:rgba(255,255,255,.7);text-decoration:none;font-size:.88rem;transition:color .15s;}
        .back-link:hover{color:var(--white);}
    </style>
</head>
<body>
<div class="login-wrap">
    <div class="login-logo">
        <div class="login-logo-icon"><i class="fas fa-building"></i></div>
        <h1>ДомУчет</h1>
        <p>Система управления ЖКХ</p>
    </div>

    <div class="login-card">
        <?php if ($error): ?>
            <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= escape($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" placeholder="example@mail.com" required value="<?= escape($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Пароль</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login"><i class="fas fa-sign-in-alt"></i> Войти в систему</button>
        </form>

        <div style="margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--gray-200);text-align:center;font-size:.88rem;color:var(--gray-600);">
            Нет аккаунта? <a href="register.php" style="color:var(--blue);text-decoration:none;font-weight:700;">Подать заявку на регистрацию →</a>
        </div>
    </div>

    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> Вернуться на главную</a>
</div>

<script src="assets/js/toast.js"></script>
<?php $f = getFlash(); if (!empty($f)): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ showToast(<?= json_encode($f['msg']) ?>, <?= json_encode($f['type']) ?>); });</script>
<?php endif; ?>
</body>
</html>
