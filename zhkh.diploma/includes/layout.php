<?php
/**
 * Общие функции разметки — шапка и футер в едином стиле
 */

function getAvatarUrl($user) {
    if (empty($user['avatar_path'])) return null;
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return $protocol . '://' . $host . $base . '/uploads/' . $user['avatar_path'];
}

function renderHeader($user, $activePage = '') {
    $avatarUrl = getAvatarUrl($user);
    $roleNames = ['admin'=>'Администратор','resident'=>'Жилец'];
    $roleName  = $roleNames[$user['role_name']] ?? $user['role_name'];
    $isAdmin   = ($user['role_name'] === 'admin');

    $nav = [
        ['href'=>'meters.php',   'icon'=>'fa-tachometer-alt',      'label'=>'Счётчики'],
        ['href'=>'invoices.php', 'icon'=>'fa-file-invoice-dollar',  'label'=>'Квитанции'],
    ];
    if ($isAdmin) {
        $nav[] = ['href'=>'admin/index.php','icon'=>'fa-cog','label'=>'Админ'];
    }

    ob_start(); ?>
<header class="site-header">
    <div class="header-top">
        <a href="index.php" class="logo">
            <div class="logo-icon"><i class="fas fa-building"></i></div>
            <span class="logo-text">ДомУчет</span>
        </a>
        <nav class="header-nav">
            <a href="index.php" class="<?= $activePage==='index'?'active':'' ?>">Главная</a>
            <?php foreach ($nav as $n): ?>
                <a href="<?= $n['href'] ?>" class="<?= $activePage===pathinfo($n['href'],PATHINFO_FILENAME)?'active':'' ?>"><?= $n['label'] ?></a>
            <?php endforeach; ?>
            <div class="user-chip">
                <a href="profile.php" class="user-chip-ava <?= $activePage==='profile'?'active-ava':'' ?>" title="Личный кабинет">
                    <?php if ($avatarUrl): ?>
                        <img src="<?= $avatarUrl ?>" alt="ava" style="width:34px;height:34px;object-fit:cover;border-radius:50%;display:block;">
                    <?php else: ?>
                        <svg viewBox="0 0 20 20" fill="currentColor" style="width:18px;height:18px;color:rgba(255,255,255,.8)"><circle cx="10" cy="7" r="4"/><path d="M2 18c0-4.4 3.6-8 8-8s8 3.6 8 8" stroke="none"/></svg>
                    <?php endif; ?>
                </a>
                <a href="logout.php" class="btn-sm" title="Выйти"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><path d="M13 3h4a1 1 0 011 1v12a1 1 0 01-1 1h-4M9 14l4-4-4-4M13 10H3"/></svg></a>
            </div>
        </nav>
    </div>
    <div class="cat-bar">
        <div class="cat-bar-inner">
            <a href="index.php" class="cat-item <?= $activePage==='index'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12L12 4l9 8"/><path d="M5 10v9a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1v-9"/></svg>Главная</a>
            <a href="meters.php" class="cat-item <?= $activePage==='meters'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/><path d="M7 12h1M16 12h1M12 7v1"/></svg>Счётчики</a>
            <a href="invoices.php" class="cat-item <?= $activePage==='invoices'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h4"/><path d="M12 16v-3M10 16h4"/></svg>Квитанции</a>
            <?php if ($isAdmin): ?>
                <a href="admin/index.php" class="cat-item <?= $activePage==='admin'?'active':'' ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M2 12h2M20 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>Администрирование</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<?php
    return ob_get_clean();
}

function renderFooter() {
    $flash = getFlash();
    // Cookie flash уже прочитан в initFlashFromCookie() до вывода страницы
    // Если flash пустой — проверяем $_SESSION['_flash_cookie'] куда initFlashFromCookie сложил данные
    if (empty($flash) && !empty($_SESSION['_flash_cookie'])) {
        $flash = $_SESSION['_flash_cookie'];
        unset($_SESSION['_flash_cookie']);
    }
    $flashAttr = '';
    if (!empty($flash)) {
        $flashAttr = ' data-flash-msg="' . htmlspecialchars($flash['msg'], ENT_QUOTES) . '" data-flash-type="' . htmlspecialchars($flash['type'], ENT_QUOTES) . '"';
    }
    // Inject flash attrs into body if not already set
    if ($flashAttr) {
        echo "<script>document.body.setAttribute('data-flash-msg', " . json_encode($flash['msg']) . "); document.body.setAttribute('data-flash-type', " . json_encode($flash['type']) . ");</script>";
    }
    ob_start(); ?>
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
                <div class="footer-col-title">Мы в соцсетях</div>
                <div class="footer-socials">
                    <a class="footer-social-btn vk-btn" href="https://vk.com" target="_blank" rel="noopener" title="ВКонтакте">
                        <svg viewBox="0 0 28 28" xmlns="http://www.w3.org/2000/svg"><rect width="28" height="28" rx="7" fill="#0077FF"/><path d="M15.2 19.5c-5.2 0-8.16-3.38-8.28-9H9.5c.08 4.13 1.9 5.87 3.35 6.23v-6.23h2.32v3.54c1.42-.15 2.91-1.78 3.41-3.54h2.28a7.08 7.08 0 01-3.16 4.63 7.4 7.4 0 013.5 4.37H18.7a4.64 4.64 0 00-3.02-3.22v3.22h-.48z" fill="#fff"/></svg>
                    </a>
                    <a class="footer-social-btn ok-btn" href="https://ok.ru" target="_blank" rel="noopener" title="Одноклассники">
                        <svg viewBox="0 0 28 28" xmlns="http://www.w3.org/2000/svg"><rect width="28" height="28" rx="7" fill="#F7931E"/><path d="M14 5.5a4 4 0 100 8 4 4 0 000-8zm0 5.6a1.6 1.6 0 110-3.2 1.6 1.6 0 010 3.2zm5.1 3.1a7.4 7.4 0 01-5.1 1.9 7.4 7.4 0 01-5.1-1.9.96.96 0 00-1.36 1.36 9.3 9.3 0 004.75 2.32l-3.16 3.16a.96.96 0 001.36 1.36L14 19.07l3.52 3.38a.96.96 0 001.36-1.36l-3.16-3.16a9.3 9.3 0 004.75-2.32.96.96 0 00-1.36-1.36z" fill="#fff"/></svg>
                    </a>
                    <a class="footer-social-btn tg-btn" href="https://t.me" target="_blank" rel="noopener" title="Telegram">
                        <svg viewBox="0 0 28 28" xmlns="http://www.w3.org/2000/svg"><rect width="28" height="28" rx="7" fill="#27A7E5"/><path d="M22.05 7.3L19.5 20.9c-.19.87-.7 1.08-1.42.67l-3.93-2.9-1.9 1.83c-.21.21-.38.38-.78.38l.28-3.98 7.24-6.54c.31-.28-.07-.43-.49-.16L7.22 16.88l-3.85-1.2c-.84-.26-.85-.84.17-1.24L20.9 6.7c.7-.26 1.32.16 1.15.6z" fill="#fff"/></svg>
                    </a>
                    <a class="footer-social-btn wa-btn" href="https://wa.me" target="_blank" rel="noopener" title="WhatsApp">
                        <svg viewBox="0 0 28 28" xmlns="http://www.w3.org/2000/svg"><rect width="28" height="28" rx="7" fill="#25D366"/><path d="M14 5C9.03 5 5 9.03 5 14c0 1.62.43 3.14 1.18 4.46L5 23l4.7-1.23A9 9 0 0014 23c4.97 0 9-4.03 9-9s-4.03-9-9-9zm4.6 12.63c-.19.53-1.1 1.01-1.51 1.07-.38.06-.86.08-1.38-.09-.32-.1-.73-.25-1.25-.49-2.19-.95-3.62-3.18-3.73-3.33-.11-.14-.9-1.2-.9-2.28 0-1.09.57-1.62.77-1.84.2-.22.43-.27.57-.27h.41c.13 0 .31-.05.48.37.18.43.62 1.53.67 1.64.06.11.09.24.02.38-.07.15-.1.24-.21.37-.11.13-.23.29-.33.39-.11.11-.22.23-.1.44.13.22.58.97 1.26 1.57.87.77 1.6 1.01 1.83 1.12.22.1.35.08.47-.05.13-.14.56-.65.71-.88.14-.22.28-.18.47-.11.19.07 1.23.58 1.44.69.21.11.35.16.4.25.05.1.05.6-.14 1.13z" fill="#fff"/></svg>
                    </a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> ДомУчет &mdash; Система управления ЖКХ</span>
        </div>
    </div>
</footer>
<script src="assets/js/toast.js"></script>
<?php
    return ob_get_clean();
}

function pageStyles() {
    return <<<CSS
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=PT+Sans:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/toast.css">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
    --blue:#2C6DB5;--blue-dark:#1a4f8a;--blue-mid:#3578c4;--blue-light:#EEF4FC;
    --gray-100:#F5F7FA;--gray-200:#E8ECF2;--gray-400:#9DAABF;--gray-600:#5A6880;--gray-900:#1A2540;
    --white:#FFFFFF;--green:#1DB954;--red:#EF4444;--orange:#F5900E;
}
body{font-family:'PT Sans',sans-serif;background:var(--gray-100);color:var(--gray-900);min-height:100vh;display:flex;flex-direction:column;}
/* HEADER */
.site-header{background:var(--blue);position:sticky;top:0;z-index:900;}
.header-top{max-width:1260px;margin:0 auto;padding:0 1.5rem;display:flex;align-items:center;justify-content:space-between;height:58px;}
.logo{display:flex;align-items:center;gap:.75rem;text-decoration:none;color:var(--white);}
.logo-icon{width:36px;height:36px;background:rgba(255,255,255,.18);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;}
.logo-text{font-size:1.35rem;font-weight:700;letter-spacing:-.02em;}
.header-nav{display:flex;align-items:center;gap:.25rem;}
.header-nav a{color:rgba(255,255,255,.88);text-decoration:none;font-size:.9rem;padding:.4rem .8rem;border-radius:6px;transition:background .15s;}
.header-nav a:hover,.header-nav a.active{background:rgba(255,255,255,.15);color:var(--white);}
.user-chip{display:flex;align-items:center;gap:.5rem;color:var(--white);font-size:.88rem;margin-left:.25rem;}
.user-chip-ava{width:34px;height:34px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;text-decoration:none;overflow:hidden;}
.active-ava{background:rgba(255,255,255,.35)!important;}
.user-chip-name{max-width:100px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.btn-sm{background:rgba(255,255,255,.12);color:rgba(255,255,255,.85);border:none;padding:.35rem .6rem;border-radius:6px;font-size:.82rem;cursor:pointer;text-decoration:none;transition:background .15s;font-family:inherit;}
.btn-sm:hover{background:rgba(255,255,255,.22);}
/* CAT BAR */
.cat-bar{background:rgba(0,0,0,.18);border-top:1px solid rgba(255,255,255,.1);}
.cat-bar-inner{max-width:1260px;margin:0 auto;padding:0 1.5rem;display:flex;overflow-x:auto;scrollbar-width:none;}
.cat-bar-inner::-webkit-scrollbar{display:none;}
.cat-item{display:flex;flex-direction:column;align-items:center;gap:.35rem;padding:.75rem 1.1rem;color:rgba(255,255,255,.85);text-decoration:none;font-size:.78rem;white-space:nowrap;border-bottom:2px solid transparent;transition:all .15s;flex-shrink:0;}
.cat-item i{font-size:1.1rem;}.cat-item svg{width:22px;height:22px;}
.cat-item:hover,.cat-item.active{color:var(--white);background:rgba(255,255,255,.06);border-bottom-color:rgba(255,255,255,.6);}
/* PAGE HERO */
.page-hero{background:linear-gradient(160deg,var(--blue) 0%,var(--blue-dark) 100%);padding:1.75rem 1.5rem 2.75rem;}
.page-hero-inner{max-width:1260px;margin:0 auto;}
.page-hero h1{color:var(--white);font-size:1.6rem;font-weight:700;display:flex;align-items:center;gap:.6rem;}.page-hero h1 svg{width:28px;height:28px;flex-shrink:0;}
.page-hero p{color:rgba(255,255,255,.75);font-size:.95rem;margin-top:.4rem;}
/* MAIN */
.main{max-width:1260px;margin:0 auto;padding:2rem 1.5rem 4rem;flex:1;}
/* ALERTS */
.alert{display:flex;align-items:center;gap:.75rem;padding:.9rem 1.1rem;border-radius:10px;font-size:.92rem;margin-bottom:1.25rem;}
.alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;}
.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;}
.alert-info{background:var(--blue-light);border:1px solid #bfdbfe;color:var(--blue-dark);}
/* CARDS */
.card{background:var(--white);border:1px solid var(--gray-200);border-radius:12px;padding:1.75rem;margin-bottom:1.25rem;}
.card-title{font-size:1rem;font-weight:700;margin-bottom:1.25rem;display:flex;align-items:center;gap:.5rem;color:var(--gray-900);}
.card-subtitle{font-size:.85rem;color:var(--gray-600);margin-top:-.75rem;margin-bottom:1.25rem;}
/* STATS */
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.75rem;}
.stat-card{background:var(--white);border:1px solid var(--gray-200);border-radius:12px;padding:1.25rem 1.5rem;display:flex;align-items:center;gap:1rem;}
.stat-icon{font-size:1.5rem;color:var(--blue);width:36px;text-align:center;flex-shrink:0;}
.stat-value{font-size:1.4rem;font-weight:700;line-height:1;}
.stat-label{font-size:.8rem;color:var(--gray-600);margin-top:.2rem;}
/* FORM */
.form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.25rem;}
.form-group{display:flex;flex-direction:column;gap:.35rem;}
.form-group label{font-size:.85rem;font-weight:700;color:var(--gray-600);display:flex;align-items:center;gap:.35rem;}
.form-group input,.form-group select,.form-group textarea{border:1px solid var(--gray-200);border-radius:8px;padding:.7rem .9rem;font-size:.93rem;font-family:inherit;color:var(--gray-900);outline:none;transition:border-color .15s;background:var(--white);}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(44,109,181,.1);}
.form-group input:disabled{background:var(--gray-100);color:var(--gray-400);}
.form-group small{font-size:.78rem;color:var(--gray-400);}
.btn-primary{background:var(--blue);color:var(--white);border:none;border-radius:8px;padding:.75rem 1.5rem;font-size:.92rem;font-weight:700;cursor:pointer;font-family:inherit;transition:background .15s;display:inline-flex;align-items:center;gap:.5rem;}
.btn-primary:hover{background:var(--blue-dark);}
.btn-secondary{background:var(--gray-100);color:var(--gray-900);border:1px solid var(--gray-200);border-radius:8px;padding:.7rem 1.25rem;font-size:.9rem;font-weight:600;cursor:pointer;font-family:inherit;transition:all .15s;display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;}
.btn-secondary:hover{background:var(--gray-200);}
.btn-danger{background:#fef2f2;color:var(--red);border:1px solid #fecaca;border-radius:8px;padding:.7rem 1.25rem;font-size:.9rem;font-weight:700;cursor:pointer;font-family:inherit;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;gap:.5rem;}
.btn-danger:hover{background:#fee2e2;}
.btn-row{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1.25rem;}
/* TABLE */
.table-wrap{overflow-x:auto;border-radius:8px;border:1px solid var(--gray-200);}
table{width:100%;border-collapse:collapse;font-size:.9rem;}
thead{background:var(--gray-100);}
th{padding:.85rem 1rem;text-align:left;font-size:.78rem;font-weight:700;color:var(--gray-600);text-transform:uppercase;letter-spacing:.04em;white-space:nowrap;}
td{padding:.85rem 1rem;border-top:1px solid var(--gray-200);color:var(--gray-900);}
tbody tr:hover{background:#fafbfd;}
/* BADGES */
.badge{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .6rem;border-radius:20px;font-size:.75rem;font-weight:700;}
.badge-pending{background:#FEF3C7;color:#92400E;}
.badge-approved,.badge-paid{background:#D1FAE5;color:#065F46;}
.badge-rejected,.badge-unpaid{background:#FEE2E2;color:#991B1B;}
.badge-partial{background:#FEF3C7;color:#92400E;}
.badge-new{background:var(--blue-light);color:var(--blue-dark);}
/* FILE UPLOAD */
.upload-zone{border:2px dashed var(--gray-200);border-radius:10px;padding:2rem;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;margin-top:.5rem;}
.upload-zone:hover{border-color:var(--blue);background:var(--blue-light);}
.upload-zone i{font-size:2rem;color:var(--blue);margin-bottom:.5rem;display:block;}
.upload-zone p{font-size:.9rem;color:var(--gray-600);}
.photo-preview{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:.75rem;}
.photo-preview img{width:80px;height:80px;object-fit:cover;border-radius:8px;border:2px solid var(--gray-200);}
/* METER CARDS */
.meter-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;margin-bottom:1.5rem;}
.meter-card{background:var(--white);border:1px solid var(--gray-200);border-radius:12px;padding:1.25rem 1.5rem;}
.meter-card-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--white);margin-bottom:.75rem;}
.meter-card-icon.c1{background:linear-gradient(135deg,#3578c4,#1a5ba8);}
.meter-card-icon.c2{background:linear-gradient(135deg,#F97316,#c97208);}
.meter-card-icon.c3{background:linear-gradient(135deg,#F5C518,#d4a017);}
.meter-card-icon.c4{background:linear-gradient(135deg,#EF4444,#b91c1c);}
.meter-card-label{font-size:.8rem;color:var(--gray-600);margin-bottom:.25rem;}
.meter-card-value{font-size:1.5rem;font-weight:700;}
.meter-card-unit{font-size:.8rem;color:var(--gray-400);}
.meter-card-prev{font-size:.78rem;color:var(--gray-400);margin-top:.35rem;}
/* INVOICE CARD */
.invoice-card{background:var(--white);border:1px solid var(--gray-200);border-radius:12px;padding:1.5rem;margin-bottom:1rem;border-left:4px solid var(--gray-200);}
.invoice-card.paid{border-left-color:var(--green);}
.invoice-card.unpaid{border-left-color:var(--red);}
.invoice-card.partial{border-left-color:var(--orange);}
.invoice-head{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem;margin-bottom:1rem;}
.invoice-month{font-size:1.2rem;font-weight:700;}
.invoice-amount{font-size:1.5rem;font-weight:700;}
.invoice-amount.paid{color:var(--green);}
.invoice-amount.unpaid{color:var(--red);}
.invoice-amount.partial{color:var(--orange);}
.invoice-details{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem;margin:1rem 0;}
.inv-detail{background:var(--gray-100);border-radius:8px;padding:.75rem 1rem;}
.inv-detail-label{font-size:.75rem;color:var(--gray-400);font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.3rem;}
.inv-detail-value{font-size:.97rem;font-weight:700;color:var(--gray-900);}
.invoice-breakdown{margin-top:1rem;padding-top:1rem;border-top:1px solid var(--gray-200);display:none;}
/* INFO GRID */
.info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem;}
.info-item{background:var(--gray-100);border-radius:8px;padding:1rem;border-left:3px solid var(--blue);}
.info-label{font-size:.75rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.35rem;}
.info-value{font-size:.97rem;font-weight:700;}
/* FOOTER */
.site-footer{background:#F5F7FA;border-top:1px solid var(--gray-200);padding:1.5rem 0 1rem;}
.footer-inner{max-width:1260px;margin:0 auto;padding:0 1.5rem;}
.footer-cols{display:grid;grid-template-columns:1fr 1fr 1fr 160px;gap:2rem;margin-bottom:1rem;}
.footer-col-title{font-size:.75rem;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.06em;margin-bottom:.6rem;background:none;}
.footer-cols a{display:block;font-size:.88rem;color:var(--gray-600);text-decoration:none;margin-bottom:.35rem;transition:color .15s;}
.footer-cols a:hover{color:var(--blue);}
.footer-socials{display:flex;gap:.6rem;margin-top:.35rem;flex-wrap:wrap;align-items:center;}
.footer-social-btn{width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;transition:opacity .15s,transform .15s;flex-shrink:0;background:none;border:none;padding:0;}
.footer-social-btn svg{width:28px;height:28px;display:block;}
.footer-social-btn:hover{opacity:.75;transform:translateY(-1px);}
.footer-social-btn:active{transform:scale(0.95);}
.footer-bottom{border-top:1px solid var(--gray-200);padding-top:.8rem;display:flex;justify-content:space-between;flex-wrap:wrap;gap:.5rem;}
.footer-bottom span{font-size:.8rem;color:var(--gray-400);}
@media(max-width:900px){.stats-row{grid-template-columns:repeat(2,1fr);}}@media(max-width:768px){.header-nav a:not(.active){display:none;}.footer-cols{grid-template-columns:1fr 1fr;}.stats-row{grid-template-columns:1fr 1fr;}}
</style>
CSS;
}
