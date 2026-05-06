<?php
session_start();
require_once 'includes/config.php';

if (isLoggedIn()) redirect('index.php');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim($_POST['email']     ?? '');
    $password  = trim($_POST['password']  ?? '');
    $password2 = trim($_POST['password2'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $apt_num   = trim($_POST['apartment_number'] ?? '');

    if (empty($email) || empty($password) || empty($full_name)) {
        $error = 'Заполните обязательные поля: ФИО, Email и пароль';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Некорректный адрес электронной почты';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен содержать не менее 6 символов';
    } elseif ($password !== $password2) {
        $error = 'Пароли не совпадают';
    } else {
        // Проверяем, нет ли уже такого email
        $stmt = db()->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Пользователь с таким email уже зарегистрирован';
        } else {
            // Проверяем pending-заявку
            $stmt = db()->prepare("SELECT request_id FROM registration_requests WHERE email = ? AND status = 'pending'");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Заявка с этим email уже отправлена и ожидает рассмотрения';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = db()->prepare(
                    "INSERT INTO registration_requests (email, password_hash, full_name, phone, apartment_number)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([$email, $hash, $full_name, $phone ?: null, $apt_num ?: null]);

                // Уведомить всех администраторов
                $admins = db()->query("SELECT user_id FROM users WHERE role_id = 1 AND is_active = 1")->fetchAll();
                foreach ($admins as $adm) {
                    sendNotification($adm['user_id'],
                        'Новая заявка на регистрацию',
                        "Пользователь {$full_name} ({$email}) подал заявку на регистрацию",
                        'info', 'admin.php?tab=registrations');
                }

                $success = 'Заявка отправлена! Администратор рассмотрит её в ближайшее время.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация — ДомУчет</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=PT+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/toast.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        :root{--blue:#2C6DB5;--blue-dark:#1a4f8a;--gray-100:#F5F7FA;--gray-200:#E8ECF2;--gray-400:#9DAABF;--gray-600:#5A6880;--gray-900:#1A2540;--white:#FFFFFF;}
        body{font-family:'PT Sans',sans-serif;background:linear-gradient(160deg,var(--blue) 0%,var(--blue-dark) 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;}
        .wrap{width:100%;max-width:460px;}
        .logo{text-align:center;margin-bottom:2rem;color:var(--white);}
        .logo-icon{width:60px;height:60px;background:rgba(255,255,255,.18);border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:1.6rem;margin-bottom:.75rem;}
        .logo h1{font-size:1.6rem;font-weight:700;}
        .logo p{opacity:.75;font-size:.9rem;margin-top:.25rem;}
        .card{background:var(--white);border-radius:16px;padding:2rem;box-shadow:0 24px 64px rgba(0,0,0,.2);}
        .card h2{font-size:1.2rem;font-weight:700;color:var(--gray-900);margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem;}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;}
        .form-group{display:flex;flex-direction:column;gap:.35rem;margin-bottom:1rem;}
        .form-group label{font-size:.85rem;font-weight:700;color:var(--gray-600);display:flex;align-items:center;gap:.35rem;}
        .form-group label .req{color:#e11d48;}
        .form-group input{border:1px solid var(--gray-200);border-radius:8px;padding:.75rem 1rem;font-size:.97rem;font-family:inherit;color:var(--gray-900);outline:none;transition:border-color .15s;}
        .form-group input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(44,109,181,.1);}
        .hint{font-size:.78rem;color:var(--gray-400);margin-top:.2rem;}
        .btn{width:100%;background:var(--blue);color:var(--white);border:none;border-radius:8px;padding:.85rem;font-size:1rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;display:flex;align-items:center;justify-content:center;gap:.5rem;margin-top:.5rem;}
        .btn:hover{background:var(--blue-dark);}
        .alert{border-radius:8px;padding:.75rem 1rem;font-size:.9rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.5rem;}
        .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
        .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;}
        .links{margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid var(--gray-200);text-align:center;font-size:.88rem;color:var(--gray-600);}
        .links a{color:var(--blue);text-decoration:none;font-weight:700;}
        .back-link{display:block;text-align:center;margin-top:1.25rem;color:rgba(255,255,255,.7);text-decoration:none;font-size:.88rem;transition:color .15s;}
        .back-link:hover{color:var(--white);}
        @media(max-width:480px){.form-row{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="logo">
        <div class="logo-icon"><i class="fas fa-building"></i></div>
        <h1>ДомУчет</h1>
        <p>Система управления ЖКХ</p>
    </div>

    <div class="card">
        <h2><i class="fas fa-user-plus" style="color:var(--blue)"></i> Регистрация</h2>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= escape($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= escape($success) ?></div>
            <div class="links">Вернуться к <a href="login.php">входу</a></div>
        <?php else: ?>

        <form method="POST">
            <div class="form-group">
                <label><i class="fas fa-user"></i> ФИО <span class="req">*</span></label>
                <input type="text" name="full_name" placeholder="Иванов Иван Иванович" required
                       value="<?= escape($_POST['full_name'] ?? '') ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email <span class="req">*</span></label>
                    <input type="email" name="email" placeholder="example@mail.com" required
                           value="<?= escape($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-phone"></i> Телефон</label>
                    <input type="text" name="phone" placeholder="+7 (900) 000-00-00"
                           value="<?= escape($_POST['phone'] ?? '') ?>">
                </div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-door-open"></i> Номер квартиры</label>
                <input type="text" name="apartment_number" placeholder="Например: 15"
                       value="<?= escape($_POST['apartment_number'] ?? '') ?>">
                <span class="hint">Укажите для быстрой привязки квартиры администратором</span>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Пароль <span class="req">*</span></label>
                    <input type="password" name="password" placeholder="Мин. 6 символов" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Повторите <span class="req">*</span></label>
                    <input type="password" name="password2" placeholder="••••••" required>
                </div>
            </div>
            <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Отправить заявку</button>
        </form>

        <div class="links">Уже есть аккаунт? <a href="login.php">Войти</a></div>
        <?php endif; ?>
    </div>

    <a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> На главную</a>
</div>
<script src="assets/js/toast.js"></script>
<?php if ($success): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ showToast(<?= json_encode($success) ?>, 'success', 6000); });</script>
<?php endif; ?>
<?php if ($error): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>
</body>
</html>
