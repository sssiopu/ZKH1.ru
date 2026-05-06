<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

requireLogin();
requireRole('admin');

$tab = $_GET['tab'] ?? 'dashboard';
$msg = '';
$err = '';

// ─── Обработка POST-действий ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // Добавить нового пользователя
    if ($action === 'add_user') {
        $email    = trim($_POST['email'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $roleId   = (int)($_POST['role_id'] ?? 3);
        $password = trim($_POST['password'] ?? '');
        if ($email && $fullName && $password) {
            $existing = db()->prepare("SELECT user_id FROM users WHERE email=?");
            $existing->execute([$email]);
            if ($existing->fetch()) {
                $err = 'Пользователь с таким email уже существует';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                db()->prepare("INSERT INTO users (email,password_hash,full_name,phone,role_id) VALUES(?,?,?,?,?)")
                    ->execute([$email, $hash, $fullName, $phone, $roleId]);
                $msg = 'Пользователь добавлен';
            }
        } else {
            $err = 'Заполните все обязательные поля';
        }
        $tab = 'users';
    }

    if ($action === 'toggle_user') {
        $uid   = (int)($_POST['user_id'] ?? 0);
        $state = (int)($_POST['state'] ?? 0);
        if ($uid !== (int)$_SESSION['user_id']) {
            db()->prepare("UPDATE users SET is_active=? WHERE user_id=?")->execute([$state, $uid]);
        }
        $msg = $state ? 'Пользователь активирован' : 'Пользователь заблокирован';
        $tab = 'users';
    }

    if ($action === 'change_role') {
        $uid    = (int)($_POST['user_id'] ?? 0);
        $roleId = (int)($_POST['role_id'] ?? 3);
        if ($uid !== (int)$_SESSION['user_id']) {
            db()->prepare("UPDATE users SET role_id=? WHERE user_id=?")->execute([$roleId, $uid]);
            $msg = 'Роль изменена';
        } else {
            $err = 'Нельзя изменить собственную роль';
        }
        $tab = 'users';
    }

    if ($action === 'save_tariff') {
        $tariffId = (int)($_POST['tariff_id'] ?? 0);
        $price    = (float)($_POST['price_per_unit'] ?? 0);
        if ($tariffId && $price > 0) {
            db()->prepare("UPDATE tariffs SET price_per_unit=?, updated_at=NOW() WHERE tariff_id=?")->execute([$price, $tariffId]);
            $msg = 'Тариф обновлён';
        }
        $tab = 'tariffs';
    }

    if ($action === 'assign_apartment') {
        $aptId  = (int)($_POST['apartment_id'] ?? 0);
        $userId = (int)($_POST['owner_user_id'] ?? 0) ?: null;
        db()->prepare("UPDATE apartments SET owner_user_id=? WHERE apartment_id=?")->execute([$userId, $aptId]);
        $msg = 'Квартира обновлена';
        $tab = 'apartments';
    }

    if ($action === 'save_settings') {
        foreach ($_POST['settings'] as $key => $value) {
            db()->prepare("UPDATE system_settings SET setting_value=? WHERE setting_key=?")
                ->execute([trim($value), $key]);
        }
        $msg = 'Настройки сохранены';
        $tab = 'settings';
    }

    if ($action === 'approve_reading') {
        $rid    = (int)($_POST['reading_id'] ?? 0);
        $status = $_POST['status'] ?? 'approved';
        if (in_array($status, ['approved','rejected'])) {
            db()->prepare("UPDATE meter_readings SET status=? WHERE reading_id=?")->execute([$status, $rid]);
            $msg = $status === 'approved' ? 'Показание принято' : 'Показание отклонено';
        }
        $tab = 'readings';
    }

    if ($action === 'approve_all_readings') {
        $count = db()->exec("UPDATE meter_readings SET status='approved' WHERE status='pending'");
        $msg = "Принято показаний: $count";
        $tab = 'readings';
    }

    if ($action === 'update_incident') {
        $iid    = (int)($_POST['incident_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $note   = trim($_POST['admin_note'] ?? '');
        $allowed = ['new','in_progress','completed','cancelled'];
        if ($iid && in_array($status, $allowed)) {
            db()->prepare("UPDATE incidents SET status=?, admin_note=?, updated_at=NOW() WHERE incident_id=?")
                ->execute([$status, $note, $iid]);
            $msg = 'Заявка обновлена';
        }
        $tab = 'incidents';
    }
}

// ─── Загрузка данных ────────────────────────────────────────────────────────
$stats = [];
if ($tab === 'dashboard') {
    $stats['users']       = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['apartments']  = db()->query("SELECT COUNT(*) FROM apartments")->fetchColumn();
    $stats['incidents']   = db()->query("SELECT COUNT(*) FROM incidents WHERE status NOT IN('completed','cancelled')")->fetchColumn();
    $stats['debt']        = db()->query("SELECT COALESCE(SUM(total_amount-paid_amount),0) FROM invoices WHERE payment_status!='paid'")->fetchColumn();
    $stats['pending_read']= db()->query("SELECT COUNT(*) FROM meter_readings WHERE status='pending'")->fetchColumn();
    $stats['total_paid']  = db()->query("SELECT COALESCE(SUM(paid_amount),0) FROM invoices")->fetchColumn();
    $recent_incidents = db()->query("SELECT i.*,a.apartment_number,u.full_name as reporter FROM incidents i JOIN apartments a ON i.apartment_id=a.apartment_id JOIN users u ON i.created_by=u.user_id ORDER BY i.created_at DESC LIMIT 5")->fetchAll();
    $recent_readings  = db()->query("SELECT mr.*,mt.type_name,a.apartment_number FROM meter_readings mr JOIN meter_types mt ON mr.meter_type_id=mt.meter_type_id JOIN apartments a ON mr.apartment_id=a.apartment_id WHERE mr.status='pending' ORDER BY mr.created_at DESC LIMIT 5")->fetchAll();
}

$roles = db()->query("SELECT * FROM roles ORDER BY role_id")->fetchAll();
$rolesMap = [];
foreach ($roles as $r) $rolesMap[$r['role_id']] = $r['role_name'];

$pendingRead = db()->query("SELECT COUNT(*) FROM meter_readings WHERE status='pending'")->fetchColumn();
$activeInc   = db()->query("SELECT COUNT(*) FROM incidents WHERE status NOT IN('completed','cancelled')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Панель администратора — ДомУчет</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
    --blue:#2C6DB5;--blue-dark:#1a4f8a;--blue-light:#EEF4FC;--blue-50:#f0f7ff;
    --gray-50:#F8FAFC;--gray-100:#F1F5F9;--gray-200:#E2E8F0;--gray-300:#CBD5E1;
    --gray-400:#94A3B8;--gray-500:#64748B;--gray-600:#475569;--gray-700:#334155;--gray-900:#0F172A;
    --white:#fff;--green:#16A34A;--green-light:#DCFCE7;--red:#DC2626;--red-light:#FEE2E2;
    --orange:#D97706;--orange-light:#FEF3C7;--purple:#7C3AED;--purple-light:#EDE9FE;
    --sidebar-w:240px;--topbar-h:60px;
    --shadow-sm:0 1px 3px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06);
    --shadow:0 4px 16px rgba(0,0,0,.08);--shadow-lg:0 10px 40px rgba(0,0,0,.14);
    --radius:10px;--radius-lg:14px;
}
body{font-family:'Inter',sans-serif;background:var(--gray-50);color:var(--gray-900);min-height:100vh;font-size:14px;}

.topbar{background:var(--blue);height:var(--topbar-h);display:flex;align-items:center;padding:0 1.5rem;gap:1rem;position:fixed;top:0;left:0;right:0;z-index:200;box-shadow:0 2px 16px rgba(0,0,0,.18);}
.topbar-logo{color:#fff;font-size:1.15rem;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:.6rem;}
.logo-box{width:32px;height:32px;background:rgba(255,255,255,.2);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.9rem;}
.topbar-badge{background:rgba(255,255,255,.18);color:#fff;font-size:.65rem;padding:.15rem .55rem;border-radius:20px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;border:1px solid rgba(255,255,255,.25);}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:.4rem;}
.topbar-btn{color:rgba(255,255,255,.85);text-decoration:none;font-size:.82rem;display:flex;align-items:center;gap:.35rem;padding:.4rem .8rem;border-radius:7px;transition:background .15s;border:none;background:transparent;cursor:pointer;font-family:inherit;}
.topbar-btn:hover{background:rgba(255,255,255,.14);color:#fff;}
.topbar-sep{width:1px;height:20px;background:rgba(255,255,255,.2);}

.layout{display:flex;margin-top:var(--topbar-h);min-height:calc(100vh - var(--topbar-h));}
.sidebar{width:var(--sidebar-w);background:#fff;border-right:1px solid var(--gray-200);position:fixed;top:var(--topbar-h);bottom:0;left:0;overflow-y:auto;z-index:100;}
.sb-section{padding:.75rem 1.25rem .25rem;font-size:.65rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.1em;margin-top:.5rem;}
.sidebar a{display:flex;align-items:center;gap:.65rem;padding:.65rem 1.25rem;color:var(--gray-600);text-decoration:none;font-size:.875rem;font-weight:500;transition:all .13s;position:relative;border-left:3px solid transparent;}
.sidebar a:hover{background:var(--gray-50);color:var(--gray-900);border-left-color:var(--gray-200);}
.sidebar a.active{background:var(--blue-50);color:var(--blue);font-weight:600;border-left-color:var(--blue);}
.si{width:18px;text-align:center;flex-shrink:0;}
.sb-badge{margin-left:auto;background:var(--red);color:#fff;border-radius:12px;font-size:.65rem;padding:.1rem .42rem;font-weight:700;min-width:18px;text-align:center;}

.content{flex:1;margin-left:var(--sidebar-w);padding:1.75rem 2rem;}
.page-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap;}
.page-title{font-size:1.3rem;font-weight:700;display:flex;align-items:center;gap:.65rem;}
.title-icon{width:38px;height:38px;background:var(--blue-light);border-radius:10px;display:flex;align-items:center;justify-content:center;color:var(--blue);font-size:.95rem;flex-shrink:0;}

.alert{border-radius:var(--radius);padding:.75rem 1rem;font-size:.875rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:.6rem;font-weight:500;}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534;}
.alert-error  {background:#fef2f2;border:1px solid #fecaca;color:#991b1b;}

.stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:1rem;margin-bottom:1.75rem;}
.stat-card{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:1.2rem 1.4rem;display:flex;align-items:flex-start;gap:.9rem;box-shadow:var(--shadow-sm);}
.stat-icon-wrap{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.05rem;flex-shrink:0;}
.ic-blue{background:var(--blue-light);color:var(--blue);}
.ic-green{background:var(--green-light);color:var(--green);}
.ic-orange{background:var(--orange-light);color:var(--orange);}
.ic-red{background:var(--red-light);color:var(--red);}
.ic-purple{background:var(--purple-light);color:var(--purple);}
.stat-label{font-size:.7rem;font-weight:600;color:var(--gray-500);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.3rem;}
.stat-value{font-size:1.55rem;font-weight:700;line-height:1;}
.v-warn{color:var(--red);}
.v-ok{color:var(--green);}

.card{background:#fff;border:1px solid var(--gray-200);border-radius:var(--radius-lg);padding:1.5rem;margin-bottom:1.5rem;box-shadow:var(--shadow-sm);}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;}
.card-title{font-size:.95rem;font-weight:700;display:flex;align-items:center;gap:.5rem;}
.card-title i{color:var(--blue);}

.tbl-wrap{overflow-x:auto;border-radius:var(--radius);border:1px solid var(--gray-200);}
table{width:100%;border-collapse:collapse;font-size:.84rem;}
thead{background:var(--gray-50);}
th{color:var(--gray-500);font-weight:600;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;padding:.7rem 1rem;text-align:left;white-space:nowrap;border-bottom:1px solid var(--gray-200);}
td{padding:.75rem 1rem;border-bottom:1px solid var(--gray-100);vertical-align:middle;color:var(--gray-700);}
tr:last-child td{border-bottom:none;}
tbody tr:hover td{background:var(--gray-50);}
.td-main{font-weight:600;color:var(--gray-900);}
.td-sm{font-size:.78rem;color:var(--gray-400);}

.badge{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .58rem;border-radius:6px;font-size:.7rem;font-weight:700;white-space:nowrap;}
.badge-pending,.badge-new{background:var(--orange-light);color:#92400e;}
.badge-approved,.badge-active,.badge-completed{background:var(--green-light);color:#166534;}
.badge-rejected,.badge-blocked,.badge-cancelled{background:var(--red-light);color:#991b1b;}
.badge-in_progress{background:var(--blue-light);color:var(--blue-dark);}
.badge-admin{background:var(--purple-light);color:#5b21b6;}
.badge-worker{background:#dbeafe;color:#1e40af;}
.badge-resident{background:var(--gray-100);color:var(--gray-600);}
.badge-you{background:var(--orange-light);color:#92400e;font-size:.65rem;}
tr.row-me td{background:var(--blue-50) !important;}
tr.row-me td:first-child{border-left:3px solid var(--blue);}

.form-group{display:flex;flex-direction:column;gap:.3rem;margin-bottom:.85rem;}
.form-group label{font-size:.8rem;font-weight:600;color:var(--gray-600);}
.form-group input,.form-group select,.form-group textarea{border:1px solid var(--gray-300);border-radius:8px;padding:.58rem .9rem;font-size:.875rem;font-family:inherit;color:var(--gray-900);background:#fff;outline:none;transition:border-color .15s,box-shadow .15s;}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(44,109,181,.12);}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;}
.inline-select{border:1px solid var(--gray-300);border-radius:7px;padding:.28rem .55rem;font-size:.8rem;font-family:inherit;background:#fff;color:var(--gray-900);cursor:pointer;outline:none;}
.inline-select:focus{border-color:var(--blue);}

.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.52rem 1.1rem;border-radius:8px;font-size:.84rem;font-weight:600;font-family:inherit;cursor:pointer;border:none;transition:all .15s;text-decoration:none;white-space:nowrap;}
.btn-primary{background:var(--blue);color:#fff;}.btn-primary:hover{background:var(--blue-dark);}
.btn-success{background:var(--green);color:#fff;}.btn-success:hover{background:#15803d;}
.btn-danger{background:var(--red);color:#fff;}.btn-danger:hover{background:#b91c1c;}
.btn-ghost{background:var(--gray-100);color:var(--gray-600);border:1px solid var(--gray-200);}.btn-ghost:hover{background:var(--gray-200);color:var(--gray-900);}
.btn-sm{padding:.32rem .7rem;font-size:.78rem;border-radius:7px;}

.tab-pills{display:flex;gap:.2rem;background:var(--gray-100);border-radius:9px;padding:.22rem;margin-bottom:1.25rem;width:fit-content;}
.tab-pill{padding:.38rem 1rem;border-radius:7px;font-size:.82rem;font-weight:600;color:var(--gray-500);cursor:pointer;transition:all .15s;border:none;background:transparent;font-family:inherit;text-decoration:none;}
.tab-pill:hover,.tab-pill.active{background:#fff;color:var(--gray-900);box-shadow:var(--shadow-sm);}
.tab-pill.active{color:var(--blue);}

.empty-state{text-align:center;padding:2.5rem 2rem;color:var(--gray-400);}
.empty-state i{font-size:2rem;margin-bottom:.6rem;display:block;}
.empty-state p{font-size:.88rem;}

.modal-bg{display:none;position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:500;align-items:center;justify-content:center;backdrop-filter:blur(2px);}
.modal-bg.open{display:flex;}
.modal{background:#fff;border-radius:var(--radius-lg);padding:1.75rem 2rem;width:calc(100% - 2rem);max-width:520px;box-shadow:var(--shadow-lg);animation:min .18s ease;}
@keyframes min{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.modal-title{font-size:1rem;font-weight:700;margin-bottom:1.4rem;display:flex;align-items:center;gap:.5rem;}
.modal-title i{color:var(--blue);}
.modal-footer{display:flex;gap:.6rem;margin-top:1.3rem;justify-content:flex-end;}

.two-col{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;}
</style>
</head>
<body>

<div class="topbar">
    <a href="index.php?tab=dashboard" class="topbar-logo">
        <span class="logo-box"><i class="fas fa-building"></i></span> ДомУчет
    </a>
    <span class="topbar-badge"><i class="fas fa-shield-alt"></i> Администратор</span>
    <div class="topbar-right">
        <a href="../index.php" class="topbar-btn"><i class="fas fa-home"></i> Сайт</a>
        <div class="topbar-sep"></div>
        <a href="../logout.php" class="topbar-btn"><i class="fas fa-sign-out-alt"></i> Выход</a>
    </div>
</div>

<div class="layout">
<nav class="sidebar">
    <div class="sb-section">Управление</div>
    <a href="index.php?tab=dashboard" class="<?= $tab==='dashboard'?'active':'' ?>"><span class="si"><i class="fas fa-chart-pie"></i></span> Дашборд</a>
    <a href="index.php?tab=users" class="<?= $tab==='users'?'active':'' ?>"><span class="si"><i class="fas fa-users"></i></span> Пользователи</a>
    <a href="index.php?tab=apartments" class="<?= $tab==='apartments'?'active':'' ?>"><span class="si"><i class="fas fa-door-open"></i></span> Квартиры</a>

    <div class="sb-section">ЖКХ</div>
    <a href="index.php?tab=incidents" class="<?= $tab==='incidents'?'active':'' ?>">
        <span class="si"><i class="fas fa-tools"></i></span> Заявки
        <?php if ($activeInc > 0): ?><span class="sb-badge"><?= $activeInc ?></span><?php endif; ?>
    </a>
    <a href="index.php?tab=readings" class="<?= $tab==='readings'?'active':'' ?>">
        <span class="si"><i class="fas fa-tachometer-alt"></i></span> Показания
        <?php if ($pendingRead > 0): ?><span class="sb-badge"><?= $pendingRead ?></span><?php endif; ?>
    </a>
    <a href="index.php?tab=invoices" class="<?= $tab==='invoices'?'active':'' ?>"><span class="si"><i class="fas fa-file-invoice-dollar"></i></span> Счета</a>
    <a href="index.php?tab=tariffs" class="<?= $tab==='tariffs'?'active':'' ?>"><span class="si"><i class="fas fa-ruble-sign"></i></span> Тарифы</a>

    <div class="sb-section">Система</div>
    <a href="index.php?tab=settings" class="<?= $tab==='settings'?'active':'' ?>"><span class="si"><i class="fas fa-cog"></i></span> Настройки</a>
</nav>

<main class="content">

<?php if ($msg): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= escape($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-error"><i class="fas fa-times-circle"></i> <?= escape($err) ?></div><?php endif; ?>

<?php if ($tab === 'dashboard'): /* ===== DASHBOARD ===== */ ?>
<div class="page-header">
    <div class="page-title"><span class="title-icon"><i class="fas fa-chart-pie"></i></span> Дашборд</div>
    <span class="td-sm"><?= date('d.m.Y, H:i') ?></span>
</div>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon-wrap ic-blue"><i class="fas fa-users"></i></div><div><div class="stat-label">Пользователей</div><div class="stat-value"><?= $stats['users'] ?></div></div></div>
    <div class="stat-card"><div class="stat-icon-wrap ic-blue"><i class="fas fa-door-open"></i></div><div><div class="stat-label">Квартир</div><div class="stat-value"><?= $stats['apartments'] ?></div></div></div>
    <div class="stat-card"><div class="stat-icon-wrap ic-orange"><i class="fas fa-tools"></i></div><div><div class="stat-label">Активных заявок</div><div class="stat-value <?= $stats['incidents']>0?'v-warn':'' ?>"><?= $stats['incidents'] ?></div></div></div>
    <div class="stat-card"><div class="stat-icon-wrap ic-red"><i class="fas fa-ruble-sign"></i></div><div><div class="stat-label">Общий долг</div><div class="stat-value v-warn"><?= number_format($stats['debt'],0,'.',' ') ?> ₽</div></div></div>
    <div class="stat-card"><div class="stat-icon-wrap ic-green"><i class="fas fa-check-circle"></i></div><div><div class="stat-label">Оплачено</div><div class="stat-value v-ok"><?= number_format($stats['total_paid'],0,'.',' ') ?> ₽</div></div></div>
    <?php if ($stats['pending_read'] > 0): ?>
    <div class="stat-card"><div class="stat-icon-wrap ic-orange"><i class="fas fa-tachometer-alt"></i></div><div><div class="stat-label">Показаний к проверке</div><div class="stat-value v-warn"><?= $stats['pending_read'] ?></div></div></div>
    <?php endif; ?>
</div>

<div class="two-col">
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-tools"></i> Последние заявки</div>
        <a href="index.php?tab=incidents" class="btn btn-ghost btn-sm">Все →</a>
    </div>
    <?php if (empty($recent_incidents)): ?>
        <div class="empty-state"><i class="fas fa-check-circle" style="color:var(--green)"></i><p>Нет активных заявок</p></div>
    <?php else: ?>
    <div class="tbl-wrap"><table>
        <thead><tr><th>#</th><th>Кв.</th><th>Тема</th><th>Статус</th></tr></thead>
        <tbody><?php
        $lbl=['new'=>'Новая','in_progress'=>'В работе','completed'=>'Выполнено','cancelled'=>'Отменено'];
        foreach ($recent_incidents as $inc): ?>
        <tr><td class="td-sm"><?= $inc['incident_id'] ?></td><td class="td-main"><?= escape($inc['apartment_number']) ?></td><td><?= escape(mb_strimwidth($inc['title'],0,28,'…')) ?></td><td><span class="badge badge-<?= $inc['status'] ?>"><?= $lbl[$inc['status']]??$inc['status'] ?></span></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div>
<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fas fa-tachometer-alt"></i> Показания на проверке</div>
        <a href="index.php?tab=readings" class="btn btn-ghost btn-sm">Все →</a>
    </div>
    <?php if (empty($recent_readings)): ?>
        <div class="empty-state"><i class="fas fa-check-circle" style="color:var(--green)"></i><p>Нечего проверять</p></div>
    <?php else: ?>
    <div class="tbl-wrap"><table>
        <thead><tr><th>Кв.</th><th>Тип</th><th>Месяц</th><th>Знач.</th></tr></thead>
        <tbody><?php foreach ($recent_readings as $rd): ?>
        <tr><td class="td-main"><?= escape($rd['apartment_number']) ?></td><td><?= escape($rd['type_name']) ?></td><td><?= escape($rd['reading_month']) ?></td><td><?= $rd['reading_value'] ?></td></tr>
        <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
</div>
</div>

<?php elseif ($tab === 'users'): /* ===== USERS ===== */ ?>
<div class="page-header">
    <div class="page-title"><span class="title-icon"><i class="fas fa-users"></i></span> Пользователи</div>
    <button class="btn btn-primary" onclick="openModal('addUserModal')"><i class="fas fa-plus"></i> Добавить</button>
</div>
<?php $users = db()->query("SELECT u.*,r.role_name FROM users u JOIN roles r ON u.role_id=r.role_id ORDER BY u.created_at DESC")->fetchAll(); ?>
<div class="card" style="padding:0">
<div class="tbl-wrap"><table>
<thead><tr><th>ID</th><th>Пользователь</th><th>Email</th><th>Телефон</th><th>Роль</th><th>Статус</th><th>Последний вход</th><th style="text-align:right">Действия</th></tr></thead>
<tbody>
<?php foreach ($users as $u): $isMe = $u['user_id'] == $_SESSION['user_id']; ?>
<tr class="<?= $isMe?'row-me':'' ?>">
    <td class="td-sm"><?= $u['user_id'] ?></td>
    <td><div class="td-main"><?= escape($u['full_name']) ?></div></td>
    <td><?= escape($u['email']) ?></td>
    <td><?= escape($u['phone'] ?? '—') ?></td>
    <td><?php if (!$isMe): ?><form method="POST" style="display:inline"><input type="hidden" name="action" value="change_role"><input type="hidden" name="user_id" value="<?= $u['user_id'] ?>"><select name="role_id" class="inline-select" onchange="this.form.submit()"><?php foreach ($roles as $r): if($r['role_name']==='worker') continue; ?><option value="<?= $r['role_id'] ?>" <?= $r['role_id']==$u['role_id']?'selected':'' ?>><?= escape($r['role_name']==='admin'?'Администратор':'Жилец') ?></option><?php endforeach; ?></select></form><?php else: ?><span class="badge badge-admin">Администратор</span><?php endif; ?></td>
    <td><span class="badge badge-<?= $u['is_active']?'active':'blocked' ?>"><?= $u['is_active']?'Активен':'Заблокирован' ?></span></td>
    <td class="td-sm"><?= $u['last_login'] ? date('d.m.Y H:i', strtotime($u['last_login'])) : '—' ?></td>
    <td style="text-align:right"><?php if (!$isMe): ?><form method="POST" style="display:inline"><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="user_id" value="<?= $u['user_id'] ?>"><input type="hidden" name="state" value="<?= $u['is_active']?0:1 ?>"><button class="btn btn-sm <?= $u['is_active']?'btn-danger':'btn-success' ?>"><?= $u['is_active']?'<i class="fas fa-ban"></i> Блок':'<i class="fas fa-check"></i> Разблок' ?></button></form><?php endif; ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div></div>

<div class="modal-bg" id="addUserModal">
<div class="modal">
    <div class="modal-title"><i class="fas fa-user-plus"></i> Добавить пользователя</div>
    <form method="POST"><input type="hidden" name="action" value="add_user">
    <div class="form-group"><label>ФИО *</label><input type="text" name="full_name" placeholder="Иванов Иван Иванович" required></div>
    <div class="form-group"><label>Email *</label><input type="email" name="email" placeholder="user@example.com" required></div>
    <div class="form-row">
        <div class="form-group"><label>Телефон</label><input type="tel" name="phone" placeholder="+7 (999) 000-00-00"></div>
        <div class="form-group"><label>Роль</label>
            <select name="role_id">
                <?php foreach ($roles as $r): if ($r['role_name']==='worker') continue; ?>
                <option value="<?= $r['role_id'] ?>"><?= escape($r['role_name']==='admin'?'Администратор':'Жилец') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-group"><label>Пароль *</label><input type="password" name="password" placeholder="Минимум 6 символов" required></div>
    <div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal()">Отмена</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Создать</button></div>
    </form>
</div></div>

<?php elseif ($tab === 'apartments'): /* ===== APARTMENTS ===== */ ?>
<div class="page-header">
    <div class="page-title"><span class="title-icon"><i class="fas fa-door-open"></i></span> Квартиры</div>
</div>
<?php
$apts = db()->query("SELECT a.*,u.full_name as owner_name,u.email as owner_email FROM apartments a LEFT JOIN users u ON a.owner_user_id=u.user_id ORDER BY a.apartment_number+0")->fetchAll();
$residents = db()->query("SELECT user_id,full_name,email FROM users WHERE role_id=3 AND is_active=1 ORDER BY full_name")->fetchAll();
$totalApts = count($apts);
$linkedApts = count(array_filter($apts, fn($a)=>$a['owner_user_id']));
?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);max-width:480px;margin-bottom:1.25rem;">
    <div class="stat-card"><div class="stat-icon-wrap ic-blue"><i class="fas fa-building"></i></div><div><div class="stat-label">Всего</div><div class="stat-value"><?= $totalApts ?></div></div></div>
    <div class="stat-card"><div class="stat-icon-wrap ic-green"><i class="fas fa-user-check"></i></div><div><div class="stat-label">Привязано</div><div class="stat-value v-ok"><?= $linkedApts ?></div></div></div>
    <div class="stat-card"><div class="stat-icon-wrap ic-orange"><i class="fas fa-user-slash"></i></div><div><div class="stat-label">Свободно</div><div class="stat-value <?= ($totalApts-$linkedApts)>0?'v-warn':'' ?>"><?= $totalApts-$linkedApts ?></div></div></div>
</div>
<div class="card" style="padding:0"><div class="tbl-wrap"><table>
<thead><tr><th>№ кв.</th><th>Этаж</th><th>Подъезд</th><th>Площадь</th><th>Жильцов</th><th>Владелец</th><th style="text-align:right">Действия</th></tr></thead>
<tbody><?php foreach ($apts as $a): ?>
<tr><td class="td-main">кв. <?= escape($a['apartment_number']) ?></td><td><?= $a['floor'] ?></td><td><?= $a['entrance'] ?></td><td><?= $a['area']?$a['area'].' м²':'—' ?></td><td><?= $a['residents_count'] ?></td>
<td><?php if ($a['owner_name']): ?><div style="font-weight:600"><?= escape($a['owner_name']) ?></div><div class="td-sm"><?= escape($a['owner_email']) ?></div><?php else: ?><span class="badge badge-pending"><i class="fas fa-user-slash"></i> Не привязан</span><?php endif; ?></td>
<td style="text-align:right"><button class="btn btn-ghost btn-sm" onclick="openAptModal(<?= $a['apartment_id'] ?>,<?= $a['owner_user_id']??0 ?>)"><i class="fas fa-user-edit"></i> Привязать</button></td>
</tr><?php endforeach; ?></tbody></table></div></div>

<div class="modal-bg" id="aptModal">
<div class="modal"><div class="modal-title"><i class="fas fa-user-edit"></i> Привязать жильца к квартире</div>
<form method="POST"><input type="hidden" name="action" value="assign_apartment"><input type="hidden" name="apartment_id" id="aptId" value="">
<div class="form-group"><label>Жилец (владелец аккаунта)</label><select name="owner_user_id" id="aptOwner"><option value="">— Не привязан —</option><?php foreach ($residents as $res): ?><option value="<?= $res['user_id'] ?>"><?= escape($res['full_name']) ?> — <?= escape($res['email']) ?></option><?php endforeach; ?></select></div>
<div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal()">Отмена</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button></div>
</form></div></div>

<?php elseif ($tab === 'incidents'): /* ===== INCIDENTS ===== */ ?>
<div class="page-header">
    <div class="page-title"><span class="title-icon"><i class="fas fa-tools"></i></span> Заявки жильцов</div>
</div>
<?php
$filterStatus = $_GET['status'] ?? '';
$sql = "SELECT i.*,a.apartment_number,u.full_name as reporter FROM incidents i JOIN apartments a ON i.apartment_id=a.apartment_id JOIN users u ON i.created_by=u.user_id";
if ($filterStatus) $sql .= " WHERE i.status=".db()->quote($filterStatus);
$sql .= " ORDER BY FIELD(i.status,'new','in_progress','completed','cancelled'), i.created_at DESC";
$incidents = db()->query($sql)->fetchAll();
$incStats = db()->query("SELECT status,COUNT(*) as cnt FROM incidents GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<div class="tab-pills">
    <a class="tab-pill <?= !$filterStatus?'active':'' ?>" href="index.php?tab=incidents">Все (<?= array_sum($incStats) ?>)</a>
    <a class="tab-pill <?= $filterStatus==='new'?'active':'' ?>" href="index.php?tab=incidents&status=new">Новые (<?= $incStats['new']??0 ?>)</a>
    <a class="tab-pill <?= $filterStatus==='in_progress'?'active':'' ?>" href="index.php?tab=incidents&status=in_progress">В работе (<?= $incStats['in_progress']??0 ?>)</a>
    <a class="tab-pill <?= $filterStatus==='completed'?'active':'' ?>" href="index.php?tab=incidents&status=completed">Выполнено (<?= $incStats['completed']??0 ?>)</a>
</div>
<div class="card" style="padding:0">
<?php if (empty($incidents)): ?><div class="empty-state"><i class="fas fa-check-double"></i><p>Заявок нет</p></div>
<?php else: $lbl=['new'=>'Новая','in_progress'=>'В работе','completed'=>'Выполнено','cancelled'=>'Отменено']; ?>
<div class="tbl-wrap"><table>
<thead><tr><th>#</th><th>Квартира</th><th>Заявитель</th><th>Тема</th><th>Статус</th><th>Дата</th><th style="text-align:right">Действия</th></tr></thead>
<tbody><?php foreach ($incidents as $inc): ?>
<tr><td class="td-sm"><?= $inc['incident_id'] ?></td><td class="td-main">кв. <?= escape($inc['apartment_number']) ?></td><td><?= escape($inc['reporter']) ?></td><td><?= escape($inc['title']) ?></td>
<td><span class="badge badge-<?= $inc['status'] ?>"><?= $lbl[$inc['status']]??$inc['status'] ?></span></td>
<td class="td-sm"><?= date('d.m.Y', strtotime($inc['created_at'])) ?></td>
<td style="text-align:right"><button class="btn btn-ghost btn-sm" onclick="openIncidentModal(<?= $inc['incident_id'] ?>,'<?= $inc['status'] ?>','<?= addslashes(htmlspecialchars($inc['admin_note']??'',ENT_QUOTES)) ?>')"><i class="fas fa-edit"></i> Изменить</button></td>
</tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>

<div class="modal-bg" id="incidentModal">
<div class="modal"><div class="modal-title"><i class="fas fa-edit"></i> Обновить заявку</div>
<form method="POST"><input type="hidden" name="action" value="update_incident"><input type="hidden" name="incident_id" id="incidentId" value="">
<div class="form-group"><label>Статус</label><select name="status" id="incidentStatus"><option value="new">Новая</option><option value="in_progress">В работе</option><option value="completed">Выполнено</option><option value="cancelled">Отменено</option></select></div>
<div class="form-group"><label>Комментарий</label><textarea name="admin_note" id="incidentNote" rows="3" placeholder="Что было сделано..."></textarea></div>
<div class="modal-footer"><button type="button" class="btn btn-ghost" onclick="closeModal()">Отмена</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button></div>
</form></div></div>

<?php elseif ($tab === 'readings'): /* ===== READINGS ===== */
$filterReadStatus = $_GET['status'] ?? 'pending';
$readings = db()->query("SELECT mr.*,mt.type_name,mt.unit,a.apartment_number,u.full_name as submitter FROM meter_readings mr JOIN meter_types mt ON mr.meter_type_id=mt.meter_type_id JOIN apartments a ON mr.apartment_id=a.apartment_id LEFT JOIN users u ON mr.created_by=u.user_id WHERE mr.status=".db()->quote($filterReadStatus)." ORDER BY mr.created_at DESC LIMIT 100")->fetchAll();
$readCounts = db()->query("SELECT status,COUNT(*) as cnt FROM meter_readings GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<div class="page-header">
    <div class="page-title"><span class="title-icon"><i class="fas fa-tachometer-alt"></i></span> Показания счётчиков</div>
    <?php if (($readCounts['pending']??0) > 0 && $filterReadStatus==='pending'): ?>
    <form method="POST">
        <input type="hidden" name="action" value="approve_all_readings">
        <?php $pendingCnt = (int)($readCounts['pending'] ?? 0); ?>
        <button type="submit" class="btn btn-success" onclick="return confirm('Принять все <?= $pendingCnt ?> показаний?')">
            <i class="fas fa-check-double"></i> Принять все (<?= $readCounts['pending'] ?>)
        </button>
    </form>
    <?php endif; ?>
</div>
<div class="tab-pills">
    <a class="tab-pill <?= $filterReadStatus==='pending'?'active':'' ?>" href="index.php?tab=readings&status=pending">На проверке (<?= $readCounts['pending']??0 ?>)</a>
    <a class="tab-pill <?= $filterReadStatus==='approved'?'active':'' ?>" href="index.php?tab=readings&status=approved">Принято (<?= $readCounts['approved']??0 ?>)</a>
    <a class="tab-pill <?= $filterReadStatus==='rejected'?'active':'' ?>" href="index.php?tab=readings&status=rejected">Отклонено (<?= $readCounts['rejected']??0 ?>)</a>
</div>
<div class="card" style="padding:0">
<?php if (empty($readings)): ?><div class="empty-state"><i class="fas fa-check-circle"></i><p><?= $filterReadStatus==='pending'?'Нет показаний на проверке':'Показаний нет' ?></p></div>
<?php else: ?>
<div class="tbl-wrap"><table>
<thead><tr><th>ID</th><th>Квартира</th><th>Тип</th><th>Месяц</th><th>Значение</th><th>Разница</th><th>Кто подал</th><?php if ($filterReadStatus==='pending'): ?><th style="text-align:right">Действия</th><?php endif; ?></tr></thead>
<tbody><?php foreach ($readings as $rd): ?>
<tr><td class="td-sm"><?= $rd['reading_id'] ?></td><td class="td-main">кв. <?= escape($rd['apartment_number']) ?></td><td><?= escape($rd['type_name']) ?></td><td><?= escape($rd['reading_month']) ?></td><td><strong><?= $rd['reading_value'] ?></strong> <?= escape($rd['unit']) ?></td><td><?= $rd['difference'] ?> <?= escape($rd['unit']) ?></td><td><?= escape($rd['submitter']??'—') ?></td>
<?php if ($filterReadStatus==='pending'): ?><td style="text-align:right;white-space:nowrap">
<form method="POST" style="display:inline"><input type="hidden" name="action" value="approve_reading"><input type="hidden" name="reading_id" value="<?= $rd['reading_id'] ?>"><input type="hidden" name="status" value="approved"><button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Принять</button></form>
<form method="POST" style="display:inline"><input type="hidden" name="action" value="approve_reading"><input type="hidden" name="reading_id" value="<?= $rd['reading_id'] ?>"><input type="hidden" name="status" value="rejected"><button class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Откл.</button></form>
</td><?php endif; ?>
</tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>

<?php elseif ($tab === 'invoices'): /* ===== INVOICES ===== */ ?>
<div class="page-header">
    <div class="page-title"><span class="title-icon"><i class="fas fa-file-invoice-dollar"></i></span> Счета и оплата</div>
</div>
<?php
$invoices = db()->query("SELECT inv.*,a.apartment_number,u.full_name as owner_name FROM invoices inv JOIN apartments a ON inv.apartment_id=a.apartment_id LEFT JOIN users u ON a.owner_user_id=u.user_id ORDER BY inv.created_at DESC LIMIT 100")->fetchAll();
$debtTotal   = db()->query("SELECT COALESCE(SUM(total_amount-paid_amount),0) FROM invoices WHERE payment_status!='paid'")->fetchColumn();
$paidTotal   = db()->query("SELECT COALESCE(SUM(paid_amount),0) FROM invoices WHERE payment_status='paid'")->fetchColumn();
$unpaidCount = db()->query("SELECT COUNT(*) FROM invoices WHERE payment_status!='paid'")->fetchColumn();
?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);max-width:500px;margin-bottom:1.25rem;">
    <div class="stat-card"><div class="stat-icon-wrap ic-red"><i class="fas fa-exclamation-circle"></i></div><div><div class="stat-label">Долг</div><div class="stat-value v-warn"><?= number_format($debtTotal,0,'.',' ') ?> ₽</div></div></div>
    <div class="stat-card"><div class="stat-icon-wrap ic-green"><i class="fas fa-check-circle"></i></div><div><div class="stat-label">Оплачено</div><div class="stat-value v-ok"><?= number_format($paidTotal,0,'.',' ') ?> ₽</div></div></div>
    <div class="stat-card"><div class="stat-icon-wrap ic-orange"><i class="fas fa-file-alt"></i></div><div><div class="stat-label">Неоплаченных</div><div class="stat-value v-warn"><?= $unpaidCount ?></div></div></div>
</div>
<div class="card" style="padding:0">
<?php if (empty($invoices)): ?><div class="empty-state"><i class="fas fa-file-invoice"></i><p>Счетов нет</p></div>
<?php else:
$lbl=['paid'=>'Оплачено','partial'=>'Частично','unpaid'=>'Не оплачено'];
$bm =['paid'=>'approved','partial'=>'in_progress','unpaid'=>'pending'];
?>
<div class="tbl-wrap"><table>
<thead><tr><th>#</th><th>Квартира</th><th>Владелец</th><th>Период</th><th>Сумма</th><th>Оплачено</th><th>Остаток</th><th>Статус</th><th>Дата</th></tr></thead>
<tbody><?php foreach ($invoices as $inv):
    $period = !empty($inv['billing_period']) ? $inv['billing_period'] : '—';
    $total = (float)$inv['total_amount'];
    $paid  = (float)$inv['paid_amount'];
    $rest  = $total - $paid;
?>
<tr>
    <td class="td-sm"><?= $inv['invoice_id'] ?></td>
    <td class="td-main" style="white-space:nowrap">кв. <?= escape($inv['apartment_number']) ?></td>
    <td style="white-space:nowrap"><?= escape($inv['owner_name']??'—') ?></td>
    <td style="white-space:nowrap"><?= escape($period) ?></td>
    <td style="white-space:nowrap;font-weight:600"><?= number_format($total,2,'.',' ') ?> ₽</td>
    <td style="white-space:nowrap;color:var(--green)"><?= number_format($paid,2,'.',' ') ?> ₽</td>
    <td style="white-space:nowrap;<?= $rest>0?'color:var(--red)':'' ?>"><?= number_format($rest,2,'.',' ') ?> ₽</td>
    <td><span class="badge badge-<?= $bm[$inv['payment_status']]??'pending' ?>"><?= $lbl[$inv['payment_status']]??$inv['payment_status'] ?></span></td>
    <td class="td-sm" style="white-space:nowrap"><?= date('d.m.Y', strtotime($inv['created_at'])) ?></td>
</tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</div>

<?php elseif ($tab === 'tariffs'): /* ===== TARIFFS ===== */ ?>
<div class="page-header">
    <div class="page-title"><span class="title-icon"><i class="fas fa-ruble-sign"></i></span> Тарифы</div>
</div>
<?php $tariffs = db()->query("SELECT t.*,mt.type_name,mt.unit FROM tariffs t JOIN meter_types mt ON t.meter_type_id=mt.meter_type_id ORDER BY t.meter_type_id")->fetchAll(); ?>
<div class="card" style="padding:0"><div class="tbl-wrap"><table>
<thead><tr><th>Ресурс</th><th>Единица</th><th>Действует с</th><th>Статус</th><th>Цена (₽ / ед.)</th></tr></thead>
<tbody><?php foreach ($tariffs as $t): ?>
<tr><td class="td-main"><?= escape($t['type_name']) ?></td><td><?= escape($t['unit']) ?></td><td><?= date('d.m.Y', strtotime($t['valid_from'])) ?></td><td><span class="badge badge-<?= $t['is_active']?'approved':'rejected' ?>"><?= $t['is_active']?'Активен':'Неактивен' ?></span></td>
<td><form method="POST" style="display:flex;align-items:center;gap:.6rem"><input type="hidden" name="action" value="save_tariff"><input type="hidden" name="tariff_id" value="<?= $t['tariff_id'] ?>"><input type="number" name="price_per_unit" value="<?= $t['price_per_unit'] ?>" step="0.01" min="0" style="width:120px;border:1px solid var(--gray-300);border-radius:8px;padding:.38rem .7rem;font-size:.875rem;font-family:inherit;"><button class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Сохр.</button></form></td>
</tr><?php endforeach; ?></tbody></table></div></div>

<?php elseif ($tab === 'settings'): /* ===== SETTINGS ===== */ ?>
<div class="page-header">
    <div class="page-title"><span class="title-icon"><i class="fas fa-cog"></i></span> Настройки системы</div>
</div>
<?php $settings = db()->query("SELECT * FROM system_settings WHERE setting_key != 'site_name' ORDER BY setting_id")->fetchAll(); ?>
<div class="card" style="max-width:600px">
<form method="POST"><input type="hidden" name="action" value="save_settings">
<?php foreach ($settings as $s): ?>
<div class="form-group">
    <label><?= escape($s['description']?:$s['setting_key']) ?></label>
    <?php if ($s['setting_type']==='boolean'): ?><select name="settings[<?= escape($s['setting_key']) ?>]"><option value="1" <?= $s['setting_value']=='1'?'selected':'' ?>>Включено</option><option value="0" <?= $s['setting_value']=='0'?'selected':'' ?>>Выключено</option></select>
    <?php elseif ($s['setting_type']==='integer'): ?><input type="number" name="settings[<?= escape($s['setting_key']) ?>]" value="<?= escape($s['setting_value']) ?>">
    <?php else: ?><input type="text" name="settings[<?= escape($s['setting_key']) ?>]" value="<?= escape($s['setting_value']) ?>">
    <?php endif; ?>
</div>
<?php endforeach; ?>
<button type="submit" class="btn btn-primary" style="margin-top:.5rem"><i class="fas fa-save"></i> Сохранить</button>
</form></div>

<?php endif; ?>
</main>
</div>

<script>
function openModal(id){document.getElementById(id).classList.add('open');}
function openAptModal(aptId,ownerId){document.getElementById('aptId').value=aptId;const s=document.getElementById('aptOwner');for(let o of s.options)o.selected=+o.value===+ownerId;document.getElementById('aptModal').classList.add('open');}
function openIncidentModal(id,status,note){document.getElementById('incidentId').value=id;document.getElementById('incidentStatus').value=status;document.getElementById('incidentNote').value=note;document.getElementById('incidentModal').classList.add('open');}
function closeModal(){document.querySelectorAll('.modal-bg').forEach(m=>m.classList.remove('open'));}
document.querySelectorAll('.modal-bg').forEach(bg=>bg.addEventListener('click',e=>{if(e.target===bg)closeModal();}));
document.addEventListener('keydown',e=>{if(e.key==='Escape')closeModal();});
</script>
</body>
</html>
