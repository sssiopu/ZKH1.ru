<?php
session_start();
require_once 'includes/config.php';
requireLogin();

$user = getCurrentUser();
if (!$user) {
    setFlash('Пожалуйста, войдите в систему', 'warning');
    redirect('login.php');
}
$db = db();
$success = '';
$error = '';

// ── Загрузка аватара ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['avatar'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024;

    if (!in_array($file['type'], $allowedTypes)) {
        $error = 'Недопустимый тип файла. Разрешены: JPG, PNG, GIF, WEBP';
    } elseif ($file['size'] > $maxSize) {
        $error = 'Файл слишком большой (максимум 5 МБ)';
    } else {
        $uploadDir = __DIR__ . '/uploads/avatars/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'avatar_' . $user['user_id'] . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            // Удаляем старый аватар
            if (!empty($user['avatar_path'])) {
                $old = __DIR__ . '/uploads/' . $user['avatar_path'];
                if (file_exists($old)) @unlink($old);
            }

            $avatarRelPath = 'avatars/' . $filename;
            $stmt = $db->prepare("UPDATE users SET avatar_path = ? WHERE user_id = ?");
            $stmt->execute([$avatarRelPath, $user['user_id']]);

            $_SESSION['avatar_path'] = $avatarRelPath;
            $user['avatar_path'] = $avatarRelPath;
            $success = 'Фото профиля обновлено!';
        } else {
            $error = 'Ошибка при сохранении файла. Проверьте права на папку uploads/avatars/';
        }
    }
}

// ── Обновление профиля ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if (empty($full_name)) {
        $error = 'Имя не может быть пустым';
    } else {
        $stmt = $db->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
        if ($stmt->execute([$full_name, $phone, $user['user_id']])) {
            $success = 'Профиль успешно обновлён!';
            $_SESSION['full_name'] = $full_name;
            $user['full_name'] = $full_name;
            $user['phone'] = $phone;
        } else {
            $error = 'Ошибка при обновлении профиля';
        }
    }
}

// ── Смена пароля ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $con = $_POST['confirm_password'] ?? '';

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $hash = $stmt->fetchColumn();

    if (!password_verify($cur, $hash)) {
        $error = 'Неверный текущий пароль';
    } elseif (strlen($new) < 6) {
        $error = 'Новый пароль — минимум 6 символов';
    } elseif ($new !== $con) {
        $error = 'Пароли не совпадают';
    } else {
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        if ($stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user['user_id']])) {
            $success = 'Пароль успешно изменён!';
        } else {
            $error = 'Ошибка при смене пароля';
        }
    }
}

// ── Квартира ──
$apartment = null;
if (hasRole('resident')) {
    $stmt = $db->prepare("SELECT * FROM apartments WHERE owner_user_id = ?");
    $stmt->execute([$user['user_id']]);
    $apartment = $stmt->fetch();
}

// ── Статистика ──
$stats = ['readings_count' => 0, 'last_reading' => null, 'total_paid' => 0, 'incidents_count' => 0];
if ($apartment) {
    $aid = $apartment['apartment_id'];

    $stmt = $db->prepare("SELECT COUNT(DISTINCT reading_month) FROM meter_readings WHERE apartment_id = ?");
    $stmt->execute([$aid]);
    $stats['readings_count'] = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT reading_date FROM meter_readings WHERE apartment_id = ? ORDER BY reading_date DESC LIMIT 1");
    $stmt->execute([$aid]);
    $stats['last_reading'] = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT SUM(paid_amount) FROM invoices WHERE apartment_id = ?");
    $stmt->execute([$aid]);
    $stats['total_paid'] = (float)($stmt->fetchColumn() ?: 0);

    $stmt = $db->prepare("SELECT COUNT(*) FROM incidents WHERE apartment_id = ?");
    $stmt->execute([$aid]);
    $stats['incidents_count'] = (int)$stmt->fetchColumn();
}

$roleNames = [
    'admin'      => 'Администратор системы',
    'resident'   => 'Жилец',
];

// Строим URL относительно корня сайта — не зависит от SITE_URL в config.php
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
$avatarUrl = !empty($user['avatar_path'])
    ? $protocol . '://' . $host . $scriptDir . '/uploads/' . escape($user['avatar_path'])
    : null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль — ДомУчет</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=PT+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/toast.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --blue: #2C6DB5;
            --blue-dark: #1a4f8a;
            --blue-mid: #3578c4;
            --blue-light: #EEF4FC;
            --gray-100: #F5F7FA;
            --gray-200: #E8ECF2;
            --gray-400: #9DAABF;
            --gray-600: #5A6880;
            --gray-900: #1A2540;
            --white: #FFFFFF;
            --green: #1DB954;
            --red: #EF4444;
        }
        body { font-family: 'PT Sans', sans-serif; background: var(--gray-100); color: var(--gray-900); min-height: 100vh; }

        /* ── HEADER (same as index) ── */
        .site-header { background: var(--blue); position: sticky; top: 0; z-index: 900; }
        .header-top { max-width: 1260px; margin: 0 auto; padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; height: 58px; }
        .logo { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: var(--white); }
        .logo-icon { width: 36px; height: 36px; background: rgba(255,255,255,0.18); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .logo-text { font-size: 1.35rem; font-weight: 700; letter-spacing: -0.02em; }
        .header-nav { display: flex; align-items: center; gap: 0.25rem; }
        .header-nav a { color: rgba(255,255,255,0.88); text-decoration: none; font-size: 0.9rem; padding: 0.4rem 0.8rem; border-radius: 6px; transition: background 0.15s; }
        .header-nav a:hover, .header-nav a.active { background: rgba(255,255,255,0.15); color: var(--white); }
        .user-chip { display: flex; align-items: center; gap: 0.5rem; color: var(--white); font-size: 0.88rem; }
        .user-chip-ava { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,0.4); background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; overflow: hidden; }
        .user-chip-ava img { width: 100%; height: 100%; object-fit: cover; }
        .btn-sm { background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.85); border: none; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.82rem; cursor: pointer; text-decoration: none; transition: background 0.15s; font-family: inherit; }
        .btn-sm:hover { background: rgba(255,255,255,0.22); }

        .cat-bar { background: rgba(0,0,0,0.18); border-top: 1px solid rgba(255,255,255,0.1); }
        .cat-bar-inner { max-width: 1260px; margin: 0 auto; padding: 0 1.5rem; display: flex; overflow-x: auto; scrollbar-width: none; }
        .cat-bar-inner::-webkit-scrollbar { display: none; }
        .cat-item { display: flex; flex-direction: column; align-items: center; gap: 0.35rem; padding: 0.75rem 1.1rem; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.78rem; white-space: nowrap; border-bottom: 2px solid transparent; transition: all 0.15s; flex-shrink: 0; }
        .cat-item i { font-size: 1.1rem; }
        .cat-item:hover { color: var(--white); background: rgba(255,255,255,0.06); border-bottom-color: rgba(255,255,255,0.5); }
        .cat-item.active { color: var(--white); border-bottom-color: var(--white); }

        /* ── HERO BANNER ── */
        .profile-hero { background: linear-gradient(160deg, var(--blue) 0%, var(--blue-dark) 100%); padding: 2rem 1.5rem 3rem; }
        .profile-hero-inner { max-width: 1260px; margin: 0 auto; display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
        .ava-wrap { position: relative; flex-shrink: 0; }
        .ava-img { width: 90px; height: 90px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.5); object-fit: cover; background: rgba(255,255,255,0.15); display: flex; align-items: center; justify-content: center; font-size: 2.2rem; color: rgba(255,255,255,0.7); overflow: hidden; }
        .ava-img img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .ava-edit { position: absolute; bottom: 0; right: 0; width: 28px; height: 28px; background: var(--white); color: var(--blue); border-radius: 50%; border: 2px solid var(--blue); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; cursor: pointer; transition: all 0.15s; }
        .ava-edit:hover { background: var(--blue-light); }
        .ava-edit input { display: none; }
        .hero-text { color: var(--white); }
        .hero-text h1 { font-size: 1.6rem; font-weight: 700; }
        .hero-text .role-badge { display: inline-flex; align-items: center; gap: 0.35rem; background: rgba(255,255,255,0.15); border-radius: 20px; padding: 0.2rem 0.7rem; font-size: 0.8rem; margin: 0.4rem 0 0.6rem; }
        .hero-meta { display: flex; gap: 1.25rem; flex-wrap: wrap; font-size: 0.85rem; opacity: 0.85; }
        .hero-meta span { display: flex; align-items: center; gap: 0.35rem; }

        /* ── MAIN ── */
        .main { max-width: 1260px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }

        /* alerts */
        .alert { display: flex; align-items: center; gap: 0.75rem; padding: 0.9rem 1.1rem; border-radius: 10px; font-size: 0.92rem; margin-bottom: 1.25rem; }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .alert-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

        /* tabs */
        .tabs { display: flex; gap: 0; border-bottom: 2px solid var(--gray-200); margin-bottom: 1.5rem; overflow-x: auto; scrollbar-width: none; }
        .tabs::-webkit-scrollbar { display: none; }
        .tab-btn { background: none; border: none; border-bottom: 2px solid transparent; margin-bottom: -2px; padding: 0.75rem 1.25rem; font-size: 0.9rem; font-weight: 700; color: var(--gray-600); cursor: pointer; white-space: nowrap; display: flex; align-items: center; gap: 0.4rem; transition: color 0.15s, border-color 0.15s; font-family: inherit; }
        .tab-btn:hover { color: var(--blue); }
        .tab-btn.active { color: var(--blue); border-bottom-color: var(--blue); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }

        /* section title */
        .section-title { font-size: 1.1rem; font-weight: 700; color: var(--gray-900); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }

        /* stats row */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
        .stat-card { background: var(--white); border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.25rem 1.5rem; display: flex; align-items: center; gap: 1rem; }
        .stat-icon { font-size: 1.5rem; color: var(--blue); width: 36px; text-align: center; flex-shrink: 0; }
        .stat-value { font-size: 1.4rem; font-weight: 700; line-height: 1; }
        .stat-label { font-size: 0.8rem; color: var(--gray-600); margin-top: 0.2rem; }

        /* quick actions */
        .actions-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
        .action-card { background: var(--white); border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.4rem; text-decoration: none; color: inherit; transition: border-color 0.18s, box-shadow 0.18s, transform 0.18s; display: flex; align-items: center; gap: 1rem; }
        .action-card:hover { border-color: var(--blue); box-shadow: 0 4px 16px rgba(44,109,181,0.1); transform: translateY(-2px); }
        .action-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .action-icon svg { width: 44px; height: 44px; }
        .action-title { font-weight: 700; font-size: 0.92rem; margin-bottom: 0.2rem; }
        .action-desc { font-size: 0.8rem; color: var(--gray-600); }

        /* cards */
        .card { background: var(--white); border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.75rem; margin-bottom: 1.25rem; }
        .card-title { font-size: 1rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; }

        /* form */
        .form-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.25rem; }
        .form-group { display: flex; flex-direction: column; gap: 0.35rem; }
        .form-group label { font-size: 0.85rem; font-weight: 700; color: var(--gray-600); display: flex; align-items: center; gap: 0.35rem; }
        .form-group input { border: 1px solid var(--gray-200); border-radius: 8px; padding: 0.7rem 0.9rem; font-size: 0.93rem; font-family: inherit; color: var(--gray-900); outline: none; transition: border-color 0.15s; }
        .form-group input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(44,109,181,0.12); }
        .form-group input:disabled { background: var(--gray-100); color: var(--gray-400); }
        .form-group small { font-size: 0.78rem; color: var(--gray-400); }
        .btn-primary { background: var(--blue); color: var(--white); border: none; border-radius: 8px; padding: 0.75rem 1.5rem; font-size: 0.92rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: background 0.15s; display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1.25rem; }
        .btn-primary:hover { background: var(--blue-dark); }
        .btn-danger { background: #fef2f2; color: var(--red); border: 1px solid #fecaca; border-radius: 8px; padding: 0.7rem 1.5rem; font-size: 0.9rem; font-weight: 700; cursor: pointer; font-family: inherit; transition: all 0.15s; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem; }
        .btn-danger:hover { background: #fee2e2; }

        /* info grid */
        .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; }
        .info-item { background: var(--gray-100); border-radius: 8px; padding: 1rem 1.1rem; border-left: 3px solid var(--blue); }
        .info-label { font-size: 0.75rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem; }
        .info-value { font-size: 0.97rem; font-weight: 700; color: var(--gray-900); }

        /* footer */
        .site-footer { background: #F5F7FA; border-top: 1px solid var(--gray-200); padding: 1.5rem 0 1rem; }
        .footer-inner { max-width: 1260px; margin: 0 auto; padding: 0 1.5rem; }
        .footer-cols { display: grid; grid-template-columns: 1fr 1fr 1fr 160px; gap: 2rem; margin-bottom: 1rem; }
        .footer-col-title { font-size: 0.75rem; font-weight: 700; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.6rem; background: none; }
        .footer-cols a { display: block; font-size: 0.88rem; color: var(--gray-600); text-decoration: none; margin-bottom: 0.35rem; transition: color 0.15s; }
        .footer-cols a:hover { color: var(--blue); }
        .footer-socials { display: flex; gap: 0.5rem; margin-top: 0.2rem; }
        .footer-social-btn { width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all 0.2s ease; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .footer-social-btn svg { width: 22px; height: 22px; display: block; transition: transform 0.2s ease; }
        .footer-social-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .footer-social-btn:hover svg { transform: scale(1.08); }
        .footer-social-btn:active { transform: scale(0.96); }
        .footer-social-btn.vk-btn { background: #0077FF; color: white; }
        .footer-social-btn.ok-btn { background: #FF9800; color: white; }
        .footer-social-btn.rustore-btn { background: #2196F3; color: white; }
        .footer-social-btn.tg-btn { background: #26A5E4; color: white; }
        .footer-bottom { border-top: 1px solid var(--gray-200); padding-top: 0.8rem; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; }
        .footer-bottom span { font-size: 0.8rem; color: var(--gray-400); }

        @media(max-width:768px){ .header-nav a:not(.active){ display: none; } .footer-cols{ grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="site-header">
    <div class="header-top">
        <a href="index.php" class="logo">
            <div class="logo-icon"><i class="fas fa-building"></i></div>
            <span class="logo-text">ДомУчет</span>
        </a>
        <nav class="header-nav">
            <a href="index.php">Главная</a>
            <a href="meters.php">Счётчики</a>
            <a href="invoices.php">Квитанции</a>
            <a href="profile.php" class="active">Профиль</a>
            <div class="user-chip">
                <div class="user-chip-ava">
                    <?php if ($avatarUrl): ?>
                        <img src="<?= $avatarUrl ?>" alt="ava">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <a href="logout.php" class="btn-sm"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </nav>
    </div>
    <div class="cat-bar">
        <div class="cat-bar-inner">
            <a href="index.php" class="cat-item"><i class="fas fa-home"></i>Главная</a>
            <a href="meters.php" class="cat-item"><i class="fas fa-tachometer-alt"></i>Счётчики</a>
            <a href="invoices.php" class="cat-item"><i class="fas fa-file-invoice-dollar"></i>Квитанции</a>
            <a href="profile.php" class="cat-item active"><i class="fas fa-user-circle"></i>Профиль</a>
            <?php if (hasRole('admin')): ?>
                <a href="admin/index.php" class="cat-item"><i class="fas fa-cog"></i>Администрирование</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- HERO BANNER -->
<section class="profile-hero">
    <div class="profile-hero-inner">
        <div class="ava-wrap">
            <div class="ava-img" id="avaPreviewWrap">
                <?php if ($avatarUrl): ?>
                    <img src="<?= $avatarUrl ?>" alt="avatar" id="avaPreviewImg">
                <?php else: ?>
                    <i class="fas fa-user"></i>
                <?php endif; ?>
            </div>
            <form method="POST" enctype="multipart/form-data" id="avaForm">
                <label class="ava-edit" title="Изменить фото">
                    <i class="fas fa-camera"></i>
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewAvatar(this)">
                </label>
            </form>
        </div>

        <div class="hero-text">
            <h1><?= escape($user['full_name']) ?></h1>
            <div class="role-badge">
                <i class="fas fa-user-tag"></i>
                <?= $roleNames[$user['role_name']] ?? $user['role_name'] ?>
            </div>
            <div class="hero-meta">
                <span><i class="fas fa-envelope"></i> <?= escape($user['email']) ?></span>
                <?php if ($user['phone']): ?>
                    <span><i class="fas fa-phone"></i> <?= escape($user['phone']) ?></span>
                <?php endif; ?>
                <?php if ($apartment): ?>
                    <span><i class="fas fa-home"></i> Квартира №<?= escape($apartment['apartment_number']) ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- MAIN -->
<main class="main">

    <?php if ($success): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= escape($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= escape($error) ?></div>
    <?php endif; ?>

    <!-- TABS -->
    <div class="tabs">
        <button class="tab-btn active" onclick="switchTab('overview', this)"><i class="fas fa-chart-bar"></i> Обзор</button>
        <button class="tab-btn" onclick="switchTab('personal', this)"><i class="fas fa-user-edit"></i> Личные данные</button>
        <button class="tab-btn" onclick="switchTab('security', this)"><i class="fas fa-lock"></i> Безопасность</button>
        <?php if ($apartment): ?>
            <button class="tab-btn" onclick="switchTab('apartment', this)"><i class="fas fa-home"></i> Моя квартира</button>
        <?php endif; ?>
    </div>

    <!-- TAB: Обзор -->
    <div id="tab-overview" class="tab-pane active">

        <?php if ($apartment): ?>
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <div><div class="stat-value"><?= $stats['readings_count'] ?></div><div class="stat-label">Передано показаний</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div><div class="stat-value" style="font-size:1rem;"><?= $stats['last_reading'] ? formatDate($stats['last_reading']) : 'Нет' ?></div><div class="stat-label">Последнее показание</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-ruble-sign"></i></div>
                    <div><div class="stat-value" style="font-size:1.1rem;"><?= formatMoney($stats['total_paid']) ?></div><div class="stat-label">Всего оплачено</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-tools"></i></div>
                    <div><div class="stat-value"><?= $stats['incidents_count'] ?></div><div class="stat-label">Создано заявок</div></div>
                </div>
            </div>
        <?php else: ?>
            <?php if (!hasRole('admin')): ?>
            <div class="alert alert-error" style="margin-bottom:1.5rem;"><i class="fas fa-info-circle"></i> Квартира не привязана к аккаунту. Обратитесь к администратору.</div>
            <?php else: ?>
            <div class="alert alert-info" style="margin-bottom:1.5rem;background:#EEF4FF;border-color:#93C5FD;color:#1A2540;"><i class="fas fa-shield-alt"></i> Вы вошли как администратор. Управление квартирами и квитанциями доступно в <a href="admin/index.php" style="color:#2C6DB5;font-weight:600;">панели администратора</a>.</div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="section-title"><i class="fas fa-bolt"></i> Быстрые действия</div>
        <div class="actions-grid">
            <a href="meters.php" class="action-card">
                <div class="action-icon">
                    <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="22" cy="22" r="12" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="1.5"/>
                        <circle cx="22" cy="22" r="7.5" fill="white" stroke="#93C5FD" stroke-width="1"/>
                        <path d="M22 16 L22 22 L27 24.5" stroke="#EF4444" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <circle cx="22" cy="22" r="1.5" fill="#2C6DB5"/>
                        <path d="M14 22 L15.5 22 M28.5 22 L30 22 M22 14 L22 15.5 M22 28.5 L22 30" stroke="#2C6DB5" stroke-width="1.2" stroke-linecap="round"/>
                        <path d="M18 10 C18 8.3 20 6.5 20 6.5 C20 6.5 22 8.3 22 10 C22 11.3 21 12.5 20 12.5 C19 12.5 18 11.3 18 10Z" fill="#93C5FD" stroke="#2C6DB5" stroke-width="1"/>
                    </svg>
                </div>
                <div><div class="action-title">Передать показания</div><div class="action-desc">Внести данные счётчиков</div></div>
            </a>
            <a href="invoices.php" class="action-card">
                <div class="action-icon">
                    <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="11" y="8" width="22" height="28" rx="2.5" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="1.5"/>
                        <rect x="15" y="13" width="13" height="1.5" rx="0.75" fill="#93C5FD"/>
                        <rect x="15" y="17" width="10" height="1.5" rx="0.75" fill="#93C5FD"/>
                        <rect x="15" y="21" width="11" height="1.5" rx="0.75" fill="#93C5FD"/>
                        <circle cx="29" cy="30" r="8" fill="#EF4444"/>
                        <text x="29" y="33.5" text-anchor="middle" font-size="9" font-weight="bold" fill="white" font-family="Arial">₽</text>
                        <path d="M11 33 L7 37 L11 37 Z" fill="#1a4f8a"/>
                    </svg>
                </div>
                <div><div class="action-title">Мои квитанции</div><div class="action-desc">Просмотр и оплата</div></div>
            </a>
            <a href="incidents.php" class="action-card">
                <div class="action-icon">
                    <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="21" cy="21" r="13" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="1.5"/>
                        <path d="M17 25 L25 17" stroke="#2C6DB5" stroke-width="1.8" stroke-linecap="round"/>
                        <circle cx="16" cy="26" r="2.8" fill="none" stroke="#2C6DB5" stroke-width="1.5"/>
                        <circle cx="26" cy="16" r="2.8" fill="none" stroke="#2C6DB5" stroke-width="1.5"/>
                        <circle cx="32" cy="12" r="5.5" fill="#EF4444"/>
                        <rect x="31.3" y="9" width="1.4" height="3.5" rx="0.7" fill="white"/>
                        <circle cx="32" cy="14.3" r="0.9" fill="white"/>
                    </svg>
                </div>
                <div><div class="action-title">Создать заявку</div><div class="action-desc">Сообщить о проблеме</div></div>
            </a>
        </div>
    </div>

    <!-- TAB: Личные данные -->
    <div id="tab-personal" class="tab-pane">
        <div class="card">
            <div class="card-title"><i class="fas fa-user-edit"></i> Редактирование профиля</div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> ФИО *</label>
                        <input type="text" name="full_name" value="<?= escape($user['full_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" value="<?= escape($user['email']) ?>" disabled>
                        <small>Email нельзя изменить</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Телефон</label>
                        <input type="tel" name="phone" value="<?= escape($user['phone'] ?? '') ?>" placeholder="+7 (900) 123-45-67">
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn-primary">
                    <i class="fas fa-save"></i> Сохранить изменения
                </button>
            </form>
        </div>
    </div>

    <!-- TAB: Безопасность -->
    <div id="tab-security" class="tab-pane">
        <div class="card">
            <div class="card-title"><i class="fas fa-key"></i> Смена пароля</div>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Текущий пароль *</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-key"></i> Новый пароль *</label>
                        <input type="password" name="new_password" required minlength="6">
                        <small>Минимум 6 символов</small>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-check"></i> Подтвердите пароль *</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>
                </div>
                <button type="submit" name="change_password" class="btn-primary">
                    <i class="fas fa-shield-alt"></i> Изменить пароль
                </button>
            </form>
        </div>

        <div class="card">
            <div class="card-title"><i class="fas fa-info-circle"></i> Информация о сессии</div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Последний вход</div>
                    <div class="info-value"><?= $user['last_login'] ? formatDate($user['last_login'], 'd.m.Y H:i') : 'Впервые' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Дата регистрации</div>
                    <div class="info-value"><?= formatDate($user['created_at'], 'd.m.Y') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Роль в системе</div>
                    <div class="info-value"><?= $roleNames[$user['role_name']] ?? $user['role_name'] ?></div>
                </div>
            </div>
            <a href="logout.php" class="btn-danger"><i class="fas fa-sign-out-alt"></i> Выйти из системы</a>
        </div>
    </div>

    <!-- TAB: Квартира -->
    <?php if ($apartment): ?>
    <div id="tab-apartment" class="tab-pane">
        <div class="card">
            <div class="card-title"><i class="fas fa-home"></i> Информация о квартире</div>
            <div class="info-grid">
                <div class="info-item"><div class="info-label">Номер квартиры</div><div class="info-value"><?= escape($apartment['apartment_number']) ?></div></div>
                <div class="info-item"><div class="info-label">Адрес</div><div class="info-value"><?= escape($apartment['address']) ?></div></div>
                <div class="info-item"><div class="info-label">Этаж</div><div class="info-value"><?= $apartment['floor'] ?? 'Не указан' ?></div></div>
                <div class="info-item"><div class="info-label">Подъезд</div><div class="info-value"><?= $apartment['entrance'] ?? 'Не указан' ?></div></div>
                <div class="info-item"><div class="info-label">Площадь</div><div class="info-value"><?= $apartment['area'] ? $apartment['area'] . ' м²' : 'Не указана' ?></div></div>
                <div class="info-item"><div class="info-label">Комнат</div><div class="info-value"><?= $apartment['rooms_count'] ?? '—' ?></div></div>
                <div class="info-item"><div class="info-label">Проживает</div><div class="info-value"><?= $apartment['residents_count'] ?? 1 ?> чел.</div></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</main>

<!-- FOOTER -->
<footer class="site-footer">
    <div class="footer-inner">
        <div class="footer-cols">
            <div>
                <div class="footer-col-title">Аккаунт</div>
                <a href="profile.php">Личный кабинет</a>
                <a href="logout.php">Выйти</a>
            </div>
            <div>
                <div class="footer-col-title">Услуги</div>
                <a href="meters.php">Передать показания</a>
                <a href="invoices.php">Квитанции и оплата</a>
                <a href="index.php#articles">Полезные статьи</a>
            </div>
            <div>
                <div class="footer-col-title">Система</div>
                <a href="index.php">Главная страница</a>
                <a href="index.php#about">О системе</a>
            </div>
            <div>
                <div class="footer-col-title">Поделиться</div>
                <div class="footer-socials">
                    <a class="footer-social-btn vk-btn" href="https://vk.com" target="_blank" rel="noopener" title="ВКонтакте">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.07 2H8.93C3.33 2 2 3.33 2 8.93v6.14C2 20.67 3.33 22 8.93 22h6.14C20.67 22 22 20.67 22 15.07V8.93C22 3.33 20.67 2 15.07 2zm3.08 13.27h-1.5c-.56 0-.74-.45-1.76-1.49-.88-.88-1.27-.99-1.49-.99-.3 0-.39.09-.39.52v1.36c0 .37-.12.59-1.1.59-1.62 0-3.42-.98-4.68-2.81C5.56 10.2 5.1 8.5 5.1 8.12c0-.22.09-.43.52-.43h1.5c.39 0 .54.18.69.6.76 2.2 2.04 4.12 2.56 4.12.2 0 .29-.09.29-.6V9.53c-.06-1.07-.63-1.16-.63-1.54 0-.18.15-.37.39-.37h2.36c.33 0 .45.18.45.56v3c0 .33.15.45.24.45.2 0 .37-.12.74-.49 1.14-1.28 1.96-3.25 1.96-3.25.11-.22.29-.43.68-.43h1.5c.45 0 .55.23.45.56-.19.88-2.04 3.5-2.04 3.5-.16.26-.22.37 0 .66.16.22.68.67 1.03 1.07.64.73 1.13 1.34 1.26 1.76.13.41-.09.62-.52.62z"/></svg>
                    </a>
                    <a class="footer-social-btn ok-btn" href="https://ok.ru" target="_blank" rel="noopener" title="Одноклассники">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1C5.925 1 1 5.925 1 12s4.925 11 11 11 11-4.925 11-11S18.075 1 12 1zm0 4.5a3.5 3.5 0 110 7 3.5 3.5 0 010-7zm5.5 9.25c-.4.825-1.15 1.45-2.1 1.7l1.85 1.85a.75.75 0 01-1.06 1.06l-2.19-2.19-2.19 2.19a.75.75 0 01-1.06-1.06l1.85-1.85c-.95-.25-1.7-.875-2.1-1.7a.75.75 0 011.35-.65c.35.725 1.075 1.15 1.9 1.15s1.55-.425 1.9-1.15a.75.75 0 011.35.65z"/></svg>
                    </a>
                    <a class="footer-social-btn rustore-btn" href="https://rustore.ru" target="_blank" rel="noopener" title="RuStore">
                        <svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 12l3 3 5-6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
                    </a>
                    <a class="footer-social-btn tg-btn" href="https://t.me" target="_blank" rel="noopener" title="Telegram">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8l-1.68 7.92c-.12.56-.46.7-.93.43l-2.58-1.9-1.24 1.2c-.14.14-.26.26-.52.26l.18-2.62 4.74-4.28c.2-.18-.05-.28-.32-.1L7.34 14.6l-2.52-.79c-.55-.17-.56-.55.12-.82l9.85-3.8c.46-.17.86.11.85.61z"/></svg>
                    </a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> ДомУчет &mdash; Система управления ЖКХ</span>
            <span>Дипломный проект</span>
        </div>
    </div>
</footer>

<script>
function switchTab(id, btn) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    btn.classList.add('active');
}

function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const wrap = document.getElementById('avaPreviewWrap');
        wrap.innerHTML = '<img src="' + e.target.result + '" alt="avatar" style="width:100%;height:100%;object-fit:cover;display:block;">';
        // Обновить мини-аватар в хедере
        const chips = document.querySelectorAll('.user-chip-ava');
        chips.forEach(c => { c.innerHTML = '<img src="' + e.target.result + '" alt="ava" style="width:100%;height:100%;object-fit:cover;">'; });
    };
    reader.readAsDataURL(file);
    // Авто-сабмит формы
    document.getElementById('avaForm').submit();
}
</script>
<script src="assets/js/toast.js"></script>
<?php if (!empty($success)): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ showToast(<?= json_encode($success) ?>, 'success'); });</script>
<?php endif; ?>
<?php if (!empty($error)): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>
</body>
</html>
