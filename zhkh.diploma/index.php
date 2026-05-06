<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/layout.php';
initFlashFromCookie(); // читаем cookie-flash ДО вывода HTML

$isLoggedIn = isLoggedIn();
$userName   = $isLoggedIn ? ($_SESSION['full_name'] ?? 'Пользователь') : null;
$userRole   = $isLoggedIn ? ($_SESSION['role_name'] ?? '') : null;
// Аватар — берём из БД чтобы всегда был актуальным
$userAvatar = null;
if ($isLoggedIn) {
    $_u = getCurrentUser();
    $userAvatar = !empty($_u['avatar_path']) ? UPLOAD_URL . $_u['avatar_path'] : null;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ДомУчет — Управление ЖКХ онлайн</title>
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
        }
        body { font-family: 'PT Sans', sans-serif; background: var(--gray-100); color: var(--gray-900); min-height: 100vh; }

        /* HEADER */
        .site-header { background: var(--blue); position: sticky; top: 0; z-index: 900; }
        .header-top { max-width: 1260px; margin: 0 auto; padding: 0 1.5rem; display: flex; align-items: center; justify-content: space-between; height: 58px; }
        .logo { display: flex; align-items: center; gap: 0.75rem; text-decoration: none; color: var(--white); }
        .logo-icon { width: 36px; height: 36px; background: rgba(255,255,255,0.18); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        .logo-text { font-size: 1.35rem; font-weight: 700; letter-spacing: -0.02em; }
        .header-nav { display: flex; align-items: center; gap: 0.25rem; }
        .header-nav a { color: rgba(255,255,255,0.88); text-decoration: none; font-size: 0.9rem; padding: 0.4rem 0.8rem; border-radius: 6px; transition: background 0.15s; }
        .header-nav a:hover { background: rgba(255,255,255,0.12); color: var(--white); }
        .btn-login { background: var(--white) !important; color: var(--blue) !important; font-weight: 700 !important; padding: 0.45rem 1.2rem !important; border-radius: 6px !important; margin-left: 0.5rem; }
        .btn-login:hover { background: #dbeafe !important; }
        .user-menu { display: flex; align-items: center; gap: 0.5rem; color: var(--white); font-size: 0.9rem; }
        .user-avatar { width: 34px; height: 34px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; }
        .user-name { max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .btn-sm { background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.85); border: none; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.82rem; cursor: pointer; text-decoration: none; transition: background 0.15s; font-family: inherit; }
        .btn-sm:hover { background: rgba(255,255,255,0.2); }

        /* CAT BAR */
        .cat-bar { background: rgba(0,0,0,0.18); border-top: 1px solid rgba(255,255,255,0.1); }
        .cat-bar-inner { max-width: 1260px; margin: 0 auto; padding: 0 1.5rem; display: flex; overflow-x: auto; scrollbar-width: none; }
        .cat-bar-inner::-webkit-scrollbar { display: none; }
        .cat-item { display: flex; flex-direction: column; align-items: center; gap: 0.35rem; padding: 0.75rem 1.1rem; color: rgba(255,255,255,0.85); text-decoration: none; font-size: 0.78rem; white-space: nowrap; border-bottom: 2px solid transparent; transition: all 0.15s; flex-shrink: 0; }
        .cat-item i { font-size: 1.1rem; }
        .cat-item svg { width: 22px; height: 22px; }
        .cat-item:hover { color: var(--white); border-bottom-color: rgba(255,255,255,0.5); background: rgba(255,255,255,0.06); }

        /* HERO */
        .hero { background: linear-gradient(160deg, var(--blue) 0%, var(--blue-dark) 100%); padding: 2.5rem 1.5rem 3.5rem; }
        .hero-inner { max-width: 1260px; margin: 0 auto; }
        .hero-greeting { color: rgba(255,255,255,0.7); font-size: 1rem; margin-bottom: 0.4rem; }
        .hero-title { color: var(--white); font-size: 2rem; font-weight: 700; margin-bottom: 1.5rem; }
        .search-box { display: flex; background: var(--white); border-radius: 10px; overflow: hidden; max-width: 700px; box-shadow: 0 4px 24px rgba(0,0,0,0.18); }
        .search-box input { flex: 1; border: none; outline: none; padding: 0.9rem 1.25rem; font-size: 1rem; font-family: inherit; color: var(--gray-900); }
        .search-box input::placeholder { color: var(--gray-400); }
        .search-btn { background: var(--blue); border: none; color: var(--white); padding: 0 1.4rem; font-size: 1.1rem; cursor: pointer; transition: background 0.15s; }
        .search-btn:hover { background: var(--blue-dark); }
        .search-tags { display: flex; gap: 0.5rem; margin-top: 0.9rem; flex-wrap: wrap; }
        .search-tag { background: rgba(255,255,255,0.15); color: rgba(255,255,255,0.9); border: 1px solid rgba(255,255,255,0.25); border-radius: 20px; padding: 0.3rem 0.85rem; font-size: 0.82rem; cursor: pointer; transition: background 0.15s; text-decoration: none; }
        .search-tag:hover { background: rgba(255,255,255,0.25); }

        /* MAIN */
        .main { max-width: 1260px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
        .section-title { font-size: 1.35rem; font-weight: 700; color: var(--gray-900); margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem; }
        .badge { background: var(--blue-light); color: var(--blue); font-size: 0.72rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.04em; }

        /* SEARCH RESULTS */
        #search-results { display: none; }
        .sr-item { display: flex; align-items: center; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--gray-200); cursor: pointer; text-decoration: none; color: inherit; }
        .sr-item:last-child { border-bottom: none; }
        .sr-item:hover .sr-title { color: var(--blue); }
        .sr-icon { width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .sr-icon svg { width: 36px; height: 36px; }
        .sr-title { font-weight: 700; font-size: 0.92rem; }
        .sr-sub { font-size: 0.8rem; color: var(--gray-600); }

        /* STATS */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2.5rem; }
        .stat-card { background: var(--white); border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.25rem 1.5rem; display: flex; align-items: center; gap: 1rem; }
        .stat-icon { font-size: 1.6rem; color: var(--blue); width: 40px; text-align: center; flex-shrink: 0; }
        .stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1; }
        .stat-label { font-size: 0.82rem; color: var(--gray-600); margin-top: 0.25rem; }

        /* SERVICES */
        .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 1rem; margin-bottom: 2.5rem; }
        .service-card { background: var(--white); border-radius: 12px; padding: 1.4rem 1.5rem; border: 1px solid var(--gray-200); text-decoration: none; color: inherit; cursor: pointer; transition: border-color 0.18s, box-shadow 0.18s, transform 0.18s; display: block; position: relative; }
        .service-card:hover { border-color: var(--blue); box-shadow: 0 6px 20px rgba(44,109,181,0.12); transform: translateY(-2px); }
        .service-card.locked::after { content: "\f023"; font-family: "Font Awesome 6 Free"; font-weight: 900; position: absolute; top: 0.9rem; right: 0.9rem; color: var(--gray-400); font-size: 0.75rem; }
        .sc-svg { width: 80px; height: 72px; margin-bottom: 1rem; display: block; }
        .sc-title { font-size: 0.97rem; font-weight: 700; margin-bottom: 0.35rem; }
        .sc-desc { font-size: 0.82rem; color: var(--gray-600); line-height: 1.5; }

        /* NEWS */
        .news-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2.5rem; }
        .news-card { background: var(--white); border-radius: 12px; border: 1px solid var(--gray-200); overflow: hidden; cursor: pointer; transition: border-color 0.18s, box-shadow 0.18s; text-decoration: none; color: inherit; }
        .news-card:hover { border-color: #b3cdef; box-shadow: 0 4px 16px rgba(44,109,181,0.1); }
        .news-img { height: 120px; display: flex; align-items: center; justify-content: center; background: #F0F5FF; padding: 1rem; }
        .news-img svg { width: 80px; height: 80px; }
        .news-body { padding: 0.9rem 1rem 1rem; }
        .news-title { font-size: 0.88rem; font-weight: 700; line-height: 1.4; }

        /* ABOUT */
        .about-row { display: grid; grid-template-columns: 1fr 340px; gap: 1.5rem; align-items: start; }
        .about-row-full { grid-template-columns: 1fr; }
        .about-card { background: var(--white); border: 1px solid var(--gray-200); border-radius: 12px; padding: 1.75rem 2rem; }
        .about-card h2 { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.75rem; }
        .about-card p { font-size: 0.92rem; color: var(--gray-600); line-height: 1.65; margin-bottom: 0.6rem; }
        .about-checklist { list-style: none; padding: 0; margin-top: 1rem; }
        .about-checklist li { font-size: 0.9rem; padding: 0.45rem 0; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; gap: 0.6rem; }
        .about-checklist li:last-child { border-bottom: none; }
        .about-checklist li i { color: var(--green); flex-shrink: 0; }
        .cta-card { background: linear-gradient(145deg, var(--blue), var(--blue-dark)); border-radius: 12px; padding: 1.75rem; color: var(--white); }
        .cta-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.6rem; }
        .cta-card p { font-size: 0.88rem; opacity: 0.85; line-height: 1.55; margin-bottom: 1.25rem; }
        .btn-white { display: block; background: var(--white); color: var(--blue); text-align: center; padding: 0.75rem; border-radius: 8px; font-weight: 700; font-size: 0.92rem; text-decoration: none; transition: background 0.15s; margin-bottom: 0.6rem; }
        .btn-white:hover { background: #dbeafe; }
        .btn-outline { display: block; background: transparent; color: rgba(255,255,255,0.85); text-align: center; padding: 0.65rem; border-radius: 8px; font-size: 0.88rem; text-decoration: none; border: 1px solid rgba(255,255,255,0.3); transition: background 0.15s; }
        .btn-outline:hover { background: rgba(255,255,255,0.1); }

        /* MODALS */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,25,50,0.55); z-index: 2000; align-items: center; justify-content: center; padding: 1.5rem; backdrop-filter: blur(2px); }
        .modal-overlay.active { display: flex; }
        .auth-modal { background: var(--white); border-radius: 16px; max-width: 400px; width: 100%; overflow: hidden; box-shadow: 0 24px 64px rgba(15,25,50,0.22); }
        .auth-modal-top { background: linear-gradient(135deg, var(--blue), var(--blue-dark)); padding: 2rem; text-align: center; color: var(--white); }
        .auth-lock { width: 56px; height: 56px; background: rgba(255,255,255,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; margin: 0 auto 1rem; }
        .auth-modal-top h3 { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.35rem; }
        .auth-modal-top p { font-size: 0.88rem; opacity: 0.85; }
        .auth-modal-body { padding: 1.5rem 1.75rem 2rem; }
        .auth-modal-body p { font-size: 0.9rem; color: var(--gray-600); text-align: center; margin-bottom: 1.25rem; line-height: 1.55; }
        .auth-btn-primary { display: flex; align-items: center; justify-content: center; gap: 0.5rem; background: var(--blue); color: var(--white); padding: 0.8rem; border-radius: 8px; font-weight: 700; text-decoration: none; font-size: 0.95rem; margin-bottom: 0.6rem; transition: background 0.15s; }
        .auth-btn-primary:hover { background: var(--blue-dark); }
        .auth-btn-secondary { display: flex; align-items: center; justify-content: center; gap: 0.5rem; background: var(--gray-100); color: var(--gray-900); padding: 0.75rem; border-radius: 8px; font-size: 0.9rem; text-decoration: none; transition: background 0.15s; }
        .auth-btn-secondary:hover { background: var(--gray-200); }
        .modal-cancel { display: block; text-align: center; margin-top: 0.9rem; font-size: 0.82rem; color: var(--gray-400); cursor: pointer; }
        .modal-cancel:hover { color: var(--gray-600); }
        .article-modal { background: var(--white); border-radius: 16px; max-width: 700px; width: 100%; max-height: 88vh; overflow-y: auto; box-shadow: 0 24px 64px rgba(15,25,50,0.22); }
        .article-modal-top { background: linear-gradient(135deg, var(--blue-mid), var(--blue-dark)); padding: 2rem 2.5rem 1.75rem; color: var(--white); position: sticky; top: 0; }
        .article-modal-icon { width: 72px; height: 72px; background: rgba(255,255,255,0.95); border-radius: 14px; display: flex; align-items: center; justify-content: center; margin-bottom: 1rem; padding: 8px; }
        .article-modal-icon svg { width: 56px; height: 56px; }
        .article-modal-top h2 { font-size: 1.4rem; font-weight: 700; }
        .article-close { position: absolute; top: 1.1rem; right: 1.5rem; background: rgba(255,255,255,0.15); border: none; color: var(--white); width: 34px; height: 34px; border-radius: 50%; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; transition: background 0.15s; }
        .article-close:hover { background: rgba(255,255,255,0.25); }
        .article-modal-body { padding: 2rem 2.5rem 2.5rem; }
        .article-modal-body h3 { font-size: 1rem; font-weight: 700; margin: 1.25rem 0 0.5rem; }
        .article-modal-body p { font-size: 0.93rem; color: var(--gray-600); line-height: 1.7; margin-bottom: 0.5rem; }
        .article-modal-body ul { list-style: none; padding: 0; margin-bottom: 0.5rem; }
        .article-modal-body ul li { font-size: 0.93rem; color: var(--gray-600); padding: 0.4rem 0 0.4rem 1.5rem; position: relative; line-height: 1.55; }
        .article-modal-body ul li::before { content: "✓"; position: absolute; left: 0; color: var(--green); font-weight: 700; }

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

        @media(max-width:768px){.header-nav a:not(.btn-login){display:none}.hero-title{font-size:1.4rem}.about-row{grid-template-columns:1fr}.footer-cols{grid-template-columns:1fr 1fr;}}
    </style>
</head>
<body>

<header class="site-header">
    <div class="header-top">
        <a href="index.php" class="logo">
            <div class="logo-icon"><i class="fas fa-building"></i></div>
            <span class="logo-text">ДомУчет</span>
        </a>
        <nav class="header-nav">
            <a href="#services">Услуги</a>
            <a href="#articles">Статьи</a>
            <a href="#about">О системе</a>
            <?php if ($isLoggedIn): ?>
                <div class="user-menu">
                    <a href="profile.php" class="user-avatar-link" title="Личный кабинет">
                        <div class="user-avatar">
                            <?php if ($userAvatar): ?>
                                <img src="<?= $userAvatar ?>" alt="ava" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                    </a>
                    <a href="logout.php" class="btn-sm" title="Выйти"><i class="fas fa-sign-out-alt"></i></a>
                </div>
            <?php else: ?>
                <a href="login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Войти</a>
            <?php endif; ?>
        </nav>
    </div>
    <div class="cat-bar">
        <div class="cat-bar-inner">
            <a href="#services" class="cat-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12L12 4l9 8"/><path d="M5 10v9a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1v-9"/></svg>Все услуги</a>
            <a href="<?= $isLoggedIn ? 'meters.php' : '#' ?>" onclick="<?= $isLoggedIn ? '' : "return requireAuth(event)" ?>" class="cat-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/><path d="M7 12h1M16 12h1M12 7v1"/></svg>Счётчики</a>
            <a href="<?= $isLoggedIn ? 'invoices.php' : '#' ?>" onclick="<?= $isLoggedIn ? '' : "return requireAuth(event)" ?>" class="cat-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="3" width="14" height="18" rx="2"/><path d="M9 8h6M9 12h4"/><path d="M12 16v-3M10 16h4"/></svg>Квитанции</a>
            <a href="<?= $isLoggedIn ? 'profile.php' : '#' ?>" onclick="<?= $isLoggedIn ? '' : "return requireAuth(event)" ?>" class="cat-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z"/></svg>Заявки</a>
            <a href="#articles" class="cat-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V4M4 20c0 0 2-3 8-3s8 3 8 3"/><path d="M4 4c0 0 2 3 8 3s8-3 8-3"/></svg>Статьи</a>
            <a href="#about" class="cat-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v1M12 11v5"/></svg>О системе</a>
        </div>
    </div>
</header>

<style>
/* ── HERO MASCOT LAYOUT ── */
.hero-inner { display: flex; align-items: center; gap: 2.5rem; }
.hero-text { flex: 1; min-width: 0; }
.hero-mascot-wrap { flex-shrink: 0; width: 260px; display: flex; align-items: flex-end; justify-content: center; position: relative; }

/* ── PIXEL CAT ── */
.hero-mascot-wrap { display:none; }
#catCanvas { display:block; width:60px; height:72px; image-rendering:pixelated; image-rendering:crisp-edges; cursor:pointer; flex-shrink:0; transition:transform .2s cubic-bezier(.34,1.56,.64,1); }
#catCanvas:hover { transform:scale(1.08); }
.cat-status { display:none; }
.search-with-cat { display:flex; align-items:center; margin-bottom:.5rem; }
.search-with-cat input { flex:1; height:60px; border:none; background:rgba(255,255,255,.92); color:#1A2540; font-size:.95rem; padding:0 1rem; outline:none; font-family:inherit; }
.search-with-cat input::placeholder { color:#94a3b8; }
.search-with-cat .search-btn { height:60px; padding:0 1.2rem; background:#1a4f8a; color:#fff; border:none; font-size:1.05rem; cursor:pointer; transition:background .15s; flex-shrink:0; border-radius:0 10px 10px 0; }
.search-with-cat .search-btn:hover { background:#153d6e; }

/* ── CHAT FULLSCREEN ── */
#catChat {
  display:none; position:fixed; inset:0; z-index:9999;
  background:linear-gradient(160deg,#0d2a7a 0%,#1a4ab5 40%,#2C6DB5 70%,#3d7fc4 100%);
  flex-direction:column; align-items:center; justify-content:flex-end;
  overflow:hidden;
}
#catChat.open { display:flex; animation:chatFadeIn .3s ease; }
@keyframes chatFadeIn { from{opacity:0} to{opacity:1} }

/* звёзды */
.chat-stars { position:absolute; inset:0; pointer-events:none; overflow:hidden; }
.chat-star {
  position:absolute; background:#fff; border-radius:50%;
  animation:starTwinkle var(--d,3s) ease-in-out infinite var(--delay,0s);
}
@keyframes starTwinkle { 0%,100%{opacity:.2;transform:scale(1)} 50%{opacity:1;transform:scale(1.4)} }

/* персонаж слева */
.chat-mascot-area {
  position:absolute; left:calc(50% - 370px); bottom:100px;
  display:flex; flex-direction:column; align-items:center;
}
#chatMascotCanvas {
  width:100px; height:120px;
  image-rendering:pixelated; image-rendering:crisp-edges;
  filter:drop-shadow(0 6px 18px rgba(0,0,0,.4));
  animation:mascotBob 3s ease-in-out infinite;
}
@keyframes mascotBob { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }

/* закрыть */
.chat-close-btn {
  position:absolute; top:1.25rem; right:1.25rem;
  width:36px; height:36px; border-radius:50%;
  background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.25);
  color:#fff; font-size:1rem; cursor:pointer; display:flex;
  align-items:center; justify-content:center; transition:background .15s;
}
.chat-close-btn:hover { background:rgba(255,255,255,.28); }

/* основная область */
.chat-main {
  width:100%; max-width:640px; padding:0 1.5rem 1.75rem;
  display:flex; flex-direction:column; gap:1rem; position:relative; z-index:2;
}

/* сообщения */
.chat-messages {
  max-height:calc(100vh - 200px); overflow-y:auto;
  display:flex; flex-direction:column; gap:.85rem;
  padding:.5rem 0; scrollbar-width:thin; scrollbar-color:rgba(255,255,255,.2) transparent;
}
.chat-msg { display:flex; gap:.75rem; align-items:flex-end; }
.chat-msg.user { flex-direction:row-reverse; }
.chat-bubble {
  max-width:82%; padding:.85rem 1.1rem;
  font-size:.9rem; line-height:1.55; word-break:break-word; border-radius:18px;
}
.chat-msg.bot .chat-bubble {
  background:#fff; color:#1A2540;
  border-radius:6px 18px 18px 18px;
  box-shadow:0 2px 12px rgba(0,0,0,.12);
}
.chat-msg.user .chat-bubble {
  background:rgba(255,255,255,.18);
  border:1px solid rgba(255,255,255,.3);
  color:#fff; border-radius:18px 6px 18px 18px;
}
.chat-av {
  width:32px; height:32px; border-radius:50%; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  font-size:.72rem; font-weight:700;
}
.chat-av.user-av { background:rgba(255,255,255,.2); color:#fff; }

/* печатает */
.chat-typing { display:flex; gap:5px; align-items:center; padding:.6rem .8rem; }
.chat-typing span {
  width:7px; height:7px; background:#94a3b8; border-radius:50%;
  animation:typingDot 1.2s ease-in-out infinite;
}
.chat-typing span:nth-child(2){animation-delay:.2s}
.chat-typing span:nth-child(3){animation-delay:.4s}
@keyframes typingDot{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}

/* подсказки */
.chat-hints { display:flex; flex-wrap:wrap; gap:.45rem; margin-top:.6rem; }
.chat-hint {
  background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.3);
  color:#fff; border-radius:20px; padding:.38rem .85rem; font-size:.78rem;
  cursor:pointer; transition:background .15s; font-family:inherit; white-space:nowrap;
}
.chat-hint:hover { background:rgba(255,255,255,.28); }

/* поле ввода */
.chat-input-row {
  display:flex; gap:.6rem; align-items:center;
  background:#fff; border-radius:16px;
  padding:.55rem .55rem .55rem 1.1rem;
  box-shadow:0 4px 24px rgba(0,0,0,.2);
  box-sizing:border-box; width:100%;
}
.chat-input-row input {
  flex:1; border:none; background:transparent; font-family:inherit;
  font-size:.92rem; color:#1A2540; outline:none; min-width:0;
}
.chat-input-row input::placeholder { color:#94a3b8; }
.chat-send-btn {
  width:40px; height:40px; border-radius:12px; border:none;
  background:#2C6DB5; color:#fff; cursor:pointer; font-size:1rem;
  display:flex; align-items:center; justify-content:center; transition:background .15s; flex-shrink:0;
}
.chat-send-btn:hover { background:#1a4f8a; }
.chat-send-btn:disabled { background:#94a3b8; cursor:not-allowed; }

/* название бота вверху */
.chat-topbar {
  position:absolute; top:1.2rem; left:50%; transform:translateX(-50%);
  display:flex; align-items:center; gap:.6rem; color:rgba(255,255,255,.85);
  font-size:.82rem; font-weight:600; z-index:3; white-space:nowrap;
}
.chat-topbar-dot { width:8px; height:8px; background:#4ade80; border-radius:50%; }

/* ── ROBOT CAT SVG ── */
.mascot { width: 150px; height: auto; filter: drop-shadow(0 8px 24px rgba(0,0,0,.35)); animation: mascot-float 3.5s ease-in-out infinite; transform-origin: bottom center; }
@keyframes mascot-float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }

/* eyes blink */
.eye-l, .eye-r { animation: blink 4s ease-in-out infinite; transform-origin: center; }
.eye-r { animation-delay: .08s; }
@keyframes blink { 0%,92%,100%{transform:scaleY(1)} 95%{transform:scaleY(0.05)} }

/* ears wiggle on hover */
.mascot:hover .ear-l { animation: ear-l 0.4s ease-in-out; }
.mascot:hover .ear-r { animation: ear-r 0.4s ease-in-out; }
@keyframes ear-l { 0%,100%{transform:rotate(0)} 50%{transform:rotate(-12deg)} }
@keyframes ear-r { 0%,100%{transform:rotate(0)} 50%{transform:rotate(12deg)} }

/* arm wave on hover */
.mascot:hover .arm-r { animation: wave 0.6s ease-in-out; transform-origin: 50% 10%; }
@keyframes wave { 0%,100%{transform:rotate(0)} 30%{transform:rotate(-25deg)} 70%{transform:rotate(10deg)} }

/* glow ring at base */
.mascot-glow { position: absolute; bottom: -6px; left: 50%; transform: translateX(-50%); width: 100px; height: 18px; background: radial-gradient(ellipse, rgba(255,255,255,.25) 0%, transparent 70%); border-radius: 50%; animation: glow-pulse 3.5s ease-in-out infinite; }
@keyframes glow-pulse { 0%,100%{opacity:.6;width:100px} 50%{opacity:1;width:120px} }

/* speech bubble */
.speech-bubble { position: absolute; top: -14px; right: -10px; background: #fff; color: #1A2540; font-size: .68rem; font-weight: 700; padding: .3rem .6rem; border-radius: 10px; white-space: nowrap; box-shadow: 0 2px 10px rgba(0,0,0,.2); opacity: 0; animation: bubble-pop 8s ease-in-out infinite; }
.speech-bubble::after { content:''; position:absolute; bottom:-6px; left:14px; border:6px solid transparent; border-top-color:#fff; border-bottom:0; }
@keyframes bubble-pop { 0%,15%,85%,100%{opacity:0;transform:scale(.8)} 25%,75%{opacity:1;transform:scale(1)} }

/* ── SEARCH RESULTS inside hero (no jump) ── */
#search-results { display: none; background: rgba(255,255,255,.12); backdrop-filter: blur(8px); border: 1px solid rgba(255,255,255,.2); border-radius: 12px; padding: 1rem 1.25rem; margin-top: 1rem; }
#search-results .section-title { color: rgba(255,255,255,.7); font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; margin-bottom: .6rem; }
.sr-item { display: flex; align-items: center; gap: .75rem; padding: .55rem .75rem; border-radius: 8px; cursor: pointer; text-decoration: none; color: #fff; transition: background .13s; }
.sr-item:hover { background: rgba(255,255,255,.15); }
.sr-icon { width: 30px; height: 30px; flex-shrink: 0; background: rgba(255,255,255,.15); border-radius: 7px; display: flex; align-items: center; justify-content: center; }
.sr-icon svg { width: 18px; height: 18px; }
.sr-title { font-weight: 600; font-size: .88rem; }
.sr-sub { font-size: .73rem; color: rgba(255,255,255,.6); }
.sr-close { display: inline-flex; align-items: center; gap: .35rem; color: rgba(255,255,255,.55); font-size: .78rem; cursor: pointer; margin-top: .5rem; padding: .2rem .5rem; border-radius: 6px; transition: color .13s; border: none; background: none; font-family: inherit; }
.sr-close:hover { color: #fff; }
</style>

<section class="hero">
    <div class="hero-inner">
        <div class="hero-text">
            <?php if ($isLoggedIn): ?>
                <p class="hero-greeting">Добро пожаловать,</p>
                <h1 class="hero-title"><?= escape($userName) ?></h1>
            <?php else: ?>
                <h1 class="hero-title">Управляйте домом онлайн</h1>
            <?php endif; ?>

            <!-- Search bar with pixel cat on the left -->
            <div class="search-with-cat">
                <canvas id="catCanvas" width="20" height="24" style="width:60px;height:72px;display:block;flex-shrink:0;cursor:pointer;image-rendering:pixelated;image-rendering:crisp-edges;"></canvas>
                <input type="text" id="searchInput" placeholder="Введите запрос — счётчики, заявки...">
                <button class="search-btn" onclick="doSearch()"><i class="fas fa-search"></i></button>
            </div>
            <div class="cat-status" id="catStatus">МЯУ! ЧТО ИЩЕМ?</div>

            <div class="search-tags">
                <span class="search-tag" onclick="setSearch('Передать показания')">Передать показания</span>
                <span class="search-tag" onclick="setSearch('Оплатить квитанцию')">Оплатить квитанцию</span>
                <span class="search-tag" onclick="setSearch('Создать заявку')">Создать заявку</span>
                <span class="search-tag" onclick="setSearch('Капремонт')">Капремонт</span>
                <span class="search-tag" onclick="setSearch('Управляющая компания')">УК</span>
            </div>
            <div id="search-results">
                <div class="section-title">Результаты поиска</div>
                <div id="sr-list"></div>
                <button class="sr-close" onclick="closeSearch()"><i class="fas fa-times"></i> Закрыть</button>
            </div>
        </div>

    </div>
</section>

<main class="main">

    <div class="stats-row">
        <div class="stat-card"><div class="stat-icon"><svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="10" width="20" height="16" rx="2" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2"/><path d="M4 14L14 6l10 8" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round"/><rect x="10" y="16" width="4" height="4" rx="1" fill="#93C5FD"/><rect x="16" y="16" width="4" height="4" rx="1" fill="#93C5FD"/><rect x="12" y="20" width="4" height="6" rx="1" fill="#2C6DB5"/></svg></div><div><div class="stat-value">120+</div><div class="stat-label">Квартир в системе</div></div></div>
        <div class="stat-card"><div class="stat-icon"><svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="10" cy="10" r="5" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2"/><circle cx="20" cy="10" r="4" fill="#EEF4FF" stroke="#93C5FD" stroke-width="1.5"/><path d="M2 24c0-4 3.6-6 8-6s8 2 8 6" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round" fill="#EEF4FF"/><path d="M20 18c2.5 0 5 1.5 5 5" stroke="#93C5FD" stroke-width="1.5" stroke-linecap="round"/></svg></div><div><div class="stat-value">350+</div><div class="stat-label">Пользователей</div></div></div>
        <div class="stat-card"><div class="stat-icon"><svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="14" cy="14" r="10" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2"/><path d="M14 8v6l4 2" stroke="#EF4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="14" cy="14" r="1.5" fill="#2C6DB5"/><path d="M7 14h1.5M19.5 14H21M14 7v1.5" stroke="#2C6DB5" stroke-width="1.5" stroke-linecap="round"/></svg></div><div><div class="stat-value">1250+</div><div class="stat-label">Показаний передано</div></div></div>
        <div class="stat-card"><div class="stat-icon"><svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="14" cy="14" r="10" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2"/><path d="M9 14l4 4 7-8" stroke="#1DB954" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div><div class="stat-value">98%</div><div class="stat-label">Решённых заявок</div></div></div>
    </div>

    <div id="services">
        <div class="section-title">Популярные услуги <?php if (!$isLoggedIn): ?><span class="badge">Войдите для полного доступа</span><?php endif; ?></div>
        <div class="services-grid">
            <a href="<?= $isLoggedIn ? 'meters.php' : '#' ?>" onclick="<?= $isLoggedIn ? '' : "return requireAuth(event)" ?>" class="service-card <?= $isLoggedIn ? '' : 'locked' ?>">
                <div class="sc-svg"><svg viewBox="0 0 80 72" fill="none" xmlns="http://www.w3.org/2000/svg">
  <circle cx="40" cy="36" r="22" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/>
  <circle cx="40" cy="36" r="14" fill="white" stroke="#93C5FD" stroke-width="1.5"/>
  <path d="M40 26 L40 36 L48 40" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
  <circle cx="40" cy="36" r="2.5" fill="#2C6DB5"/>
  <path d="M24 36 L27 36 M53 36 L56 36 M40 20 L40 23 M40 49 L40 52" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round"/>
  <path d="M33 16 C33 13 37 10 37 10 C37 10 41 13 41 16 C41 18.8 39.2 21 37 21 C34.8 21 33 18.8 33 16Z" fill="#93C5FD" stroke="#2C6DB5" stroke-width="1.5"/>
</svg></div>
                <div class="sc-title">Передать показания</div>
                <div class="sc-desc">Счётчики воды, газа и электроэнергии за пару минут</div>
            </a>
            <a href="<?= $isLoggedIn ? 'invoices.php' : '#' ?>" onclick="<?= $isLoggedIn ? '' : "return requireAuth(event)" ?>" class="service-card <?= $isLoggedIn ? '' : 'locked' ?>">
                <div class="sc-svg"><svg viewBox="0 0 80 72" fill="none" xmlns="http://www.w3.org/2000/svg">
  <rect x="16" y="10" width="40" height="52" rx="4" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/>
  <rect x="24" y="20" width="24" height="2.5" rx="1.25" fill="#93C5FD"/>
  <rect x="24" y="27" width="18" height="2.5" rx="1.25" fill="#93C5FD"/>
  <rect x="24" y="34" width="21" height="2.5" rx="1.25" fill="#93C5FD"/>
  <circle cx="52" cy="52" r="14" fill="#EF4444"/>
  <text x="52" y="57" text-anchor="middle" font-size="16" font-weight="bold" fill="white" font-family="Arial">₽</text>
  <path d="M16 58 L10 64 L16 64 Z" fill="#1a4f8a"/>
</svg></div>
                <div class="sc-title">Оплатить квитанции</div>
                <div class="sc-desc">Начисления и история платежей без очередей</div>
            </a>
            <a href="<?= $isLoggedIn ? 'profile.php#incidents' : '#' ?>" onclick="<?= $isLoggedIn ? '' : "return requireAuth(event)" ?>" class="service-card <?= $isLoggedIn ? '' : 'locked' ?>">
                <div class="sc-svg"><svg viewBox="0 0 80 72" fill="none" xmlns="http://www.w3.org/2000/svg">
  <circle cx="38" cy="34" r="24" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/>
  <path d="M30 42 L46 26" stroke="#2C6DB5" stroke-width="3" stroke-linecap="round"/>
  <circle cx="28" cy="44" r="5" fill="none" stroke="#2C6DB5" stroke-width="2.5"/>
  <circle cx="48" cy="24" r="5" fill="none" stroke="#2C6DB5" stroke-width="2.5"/>
  <circle cx="58" cy="18" r="10" fill="#EF4444"/>
  <rect x="57" y="13" width="2.5" height="6" rx="1.25" fill="white"/>
  <circle cx="58.25" cy="22" r="1.5" fill="white"/>
</svg></div>
                <div class="sc-title">Создать заявку</div>
                <div class="sc-desc">Сообщите о поломке и отслеживайте статус ремонта</div>
            </a>
            <a href="<?= $isLoggedIn ? 'profile.php' : '#' ?>" onclick="<?= $isLoggedIn ? '' : "return requireAuth(event)" ?>" class="service-card <?= $isLoggedIn ? '' : 'locked' ?>">
                <div class="sc-svg"><svg viewBox="0 0 80 72" fill="none" xmlns="http://www.w3.org/2000/svg">
  <circle cx="36" cy="26" r="13" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/>
  <path d="M16 64 C16 50 25 44 36 44 C47 44 56 50 56 64" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round"/>
  <rect x="52" y="46" width="18" height="14" rx="3" fill="#EF4444" stroke="#c91c1c" stroke-width="1.5"/>
  <path d="M55 46 L55 42 C55 38.7 69 38.7 69 42 L69 46" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round" fill="none"/>
  <circle cx="61" cy="53" r="2.5" fill="white"/>
</svg></div>
                <div class="sc-title">Личный кабинет</div>
                <div class="sc-desc">Профиль, история операций, настройки уведомлений</div>
            </a>
            <a href="#articles" class="service-card">
                <div class="sc-svg"><svg viewBox="0 0 80 72" fill="none" xmlns="http://www.w3.org/2000/svg">
  <path d="M40 58 C40 58 20 50 14 18 L40 22 L66 18 C60 50 40 58 40 58Z" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5" stroke-linejoin="round"/>
  <line x1="40" y1="22" x2="40" y2="58" stroke="#2C6DB5" stroke-width="2" stroke-dasharray="3 2"/>
  <rect x="22" y="28" width="13" height="2" rx="1" fill="#93C5FD"/>
  <rect x="22" y="33" width="10" height="2" rx="1" fill="#93C5FD"/>
  <rect x="22" y="38" width="12" height="2" rx="1" fill="#93C5FD"/>
  <rect x="45" y="28" width="13" height="2" rx="1" fill="#EF4444" opacity="0.7"/>
  <rect x="45" y="33" width="10" height="2" rx="1" fill="#EF4444" opacity="0.7"/>
  <rect x="45" y="38" width="12" height="2" rx="1" fill="#EF4444" opacity="0.7"/>
</svg></div>
                <div class="sc-title">Полезные статьи</div>
                <div class="sc-desc">Права жильцов, капремонт, управляющие компании</div>
            </a>
            <a href="#about" class="service-card">
                <div class="sc-svg"><svg viewBox="0 0 80 72" fill="none" xmlns="http://www.w3.org/2000/svg">
  <rect x="12" y="12" width="56" height="40" rx="5" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/>
  <rect x="30" y="52" width="20" height="6" rx="2" fill="#93C5FD"/>
  <rect x="22" y="58" width="36" height="3" rx="1.5" fill="#BFDBFE"/>
  <circle cx="40" cy="24" r="5" fill="#EF4444"/>
  <rect x="39" y="22" width="2.5" height="5" rx="1.25" fill="white"/>
  <circle cx="40.25" cy="29.5" r="1.25" fill="white"/>
  <rect x="20" y="34" width="16" height="2" rx="1" fill="#93C5FD"/>
  <rect x="20" y="39" width="20" height="2" rx="1" fill="#93C5FD"/>
  <rect x="44" y="34" width="16" height="2" rx="1" fill="#BFDBFE"/>
  <rect x="44" y="39" width="12" height="2" rx="1" fill="#BFDBFE"/>
</svg></div>
                <div class="sc-title">О системе</div>
                <div class="sc-desc">Как устроен ДомУчет и для кого он создан</div>
            </a>
        </div>
    </div>

    <div id="articles">
        <div class="section-title">Полезные статьи <span class="badge">Доступно всем</span></div>
        <div class="news-grid">
            <div class="news-card" onclick="openArticle('a1')"><div class="news-img"><svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"> <rect x="12" y="28" width="56" height="44" rx="4" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/> <path d="M12 38 L40 18 L68 38" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round"/> <rect x="28" y="44" width="10" height="10" rx="2" fill="#93C5FD"/> <rect x="42" y="44" width="10" height="10" rx="2" fill="#93C5FD"/> <rect x="32" y="58" width="16" height="14" rx="2" fill="#2C6DB5"/> <circle cx="40" cy="65" r="1.5" fill="white"/> <rect x="20" y="30" width="6" height="8" rx="1.5" fill="#BFDBFE"/></svg></div><div class="news-body"><div class="news-title">Общее имущество дома</div></div></div>
            <div class="news-card" onclick="openArticle('a2')"><div class="news-img"><svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"> <rect x="18" y="20" width="44" height="52" rx="4" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/> <rect x="26" y="32" width="28" height="3" rx="1.5" fill="#93C5FD"/> <rect x="26" y="40" width="20" height="3" rx="1.5" fill="#93C5FD"/> <rect x="26" y="48" width="24" height="3" rx="1.5" fill="#93C5FD"/> <circle cx="54" cy="56" r="10" fill="#2C6DB5"/> <path d="M49 56 L53 60 L60 52" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/> <rect x="26" y="22" width="16" height="6" rx="1.5" fill="#2C6DB5"/></svg></div><div class="news-body"><div class="news-title">Что делает управляющая организация</div></div></div>
            <div class="news-card" onclick="openArticle('a3')"><div class="news-img"><svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"> <path d="M10 70 L40 14 L70 70 Z" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5" stroke-linejoin="round"/> <rect x="33" y="52" width="14" height="18" rx="2" fill="#2C6DB5"/> <circle cx="40" cy="62" r="1.5" fill="white"/> <rect x="22" y="56" width="8" height="8" rx="2" fill="#93C5FD"/> <rect x="50" y="56" width="8" height="8" rx="2" fill="#93C5FD"/> <path d="M34 38 L40 28 L46 38" stroke="#F97316" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/> <rect x="36" y="42" width="8" height="2.5" rx="1.25" fill="#F97316"/></svg></div><div class="news-body"><div class="news-title">Работы в капремонт</div></div></div>
            <div class="news-card" onclick="openArticle('a4')"><div class="news-img"><svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"> <circle cx="40" cy="40" r="26" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/> <path d="M40 22 L43.5 33 L55 33 L45.8 39.5 L49 51 L40 44.5 L31 51 L34.2 39.5 L25 33 L36.5 33 Z" fill="#F5C518" stroke="#E6A800" stroke-width="1.5" stroke-linejoin="round"/></svg></div><div class="news-body"><div class="news-title">Качество услуг ЖКХ</div></div></div>
            <div class="news-card" onclick="openArticle('a5')"><div class="news-img"><svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"> <rect x="14" y="36" width="52" height="34" rx="4" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/> <path d="M14 44 L40 22 L66 44" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round"/> <circle cx="57" cy="26" r="8" fill="#F97316"/> <path d="M53 26 L56 29 L62 22" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/> <rect x="24" y="50" width="10" height="10" rx="2" fill="#93C5FD"/> <rect x="46" y="50" width="10" height="10" rx="2" fill="#93C5FD"/> <rect x="35" y="54" width="10" height="16" rx="2" fill="#2C6DB5"/></svg></div><div class="news-body"><div class="news-title">Планируем капремонт</div></div></div>
            <div class="news-card" onclick="openArticle('a6')"><div class="news-img"><svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"> <circle cx="28" cy="28" r="10" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/> <circle cx="52" cy="28" r="10" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/> <path d="M14 62 C14 50 22 44 28 44 C34 44 38 47 40 50 C42 47 46 44 52 44 C58 44 66 50 66 62" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round" fill="#EEF4FF"/> <path d="M33 28 L37 32 L43 24" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div class="news-body"><div class="news-title">Общее собрание собственников</div></div></div>
            <div class="news-card" onclick="openArticle('a7')"><div class="news-img"><svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"> <ellipse cx="40" cy="52" rx="24" ry="12" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/> <ellipse cx="40" cy="44" rx="24" ry="12" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/> <ellipse cx="40" cy="36" rx="24" ry="12" fill="#DBEAFE" stroke="#2C6DB5" stroke-width="2.5"/> <path d="M37 32 L37 40 M43 32 L43 40" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round"/> <path d="M34 36 C34 34.3 36.7 33 40 33 C43.3 33 46 34.3 46 36" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round"/></svg></div><div class="news-body"><div class="news-title">Спецсчёт для капремонта</div></div></div>
            <div class="news-card" onclick="openArticle('a8')"><div class="news-img"><svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"> <rect x="12" y="24" width="46" height="34" rx="4" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/> <path d="M12 30 L35 46 L58 30" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round"/> <circle cx="58" cy="54" r="12" fill="#F97316"/> <path d="M54 54 L57 57 L63 50" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><div class="news-body"><div class="news-title">Как повлиять на УК</div></div></div>
        </div>
    </div>

    <div id="about" class="about-row<?php if($isLoggedIn) echo " about-row-full"; ?>">
        <div class="about-card">
            <h2>ДомУчет — прозрачное управление домом</h2>
            <p>Сервис объединяет жильцов и управляющую компанию в одной системе. Передавайте показания, получайте квитанции, создавайте заявки и контролируйте работы онлайн.</p>
            <p><strong>Жильцам</strong> — удобно решать вопросы по дому, не выходя из квартиры.</p>
            <p><strong>Управляющей компании</strong> — видеть полную картину и быстро реагировать.</p>
            <ul class="about-checklist">
                <li><i class="fas fa-check"></i> Передавать показания счётчиков</li>
                <li><i class="fas fa-check"></i> Просматривать и оплачивать квитанции</li>
                <li><i class="fas fa-check"></i> Создавать и отслеживать заявки на ремонт</li>
                <li><i class="fas fa-check"></i> Получать уведомления о событиях дома</li>
            </ul>
        </div>
        <?php if (!$isLoggedIn): ?>
        <div class="cta-card">
            <h3>Начните пользоваться</h3>
            <p>Войдите в аккаунт или зарегистрируйтесь, чтобы получить доступ ко всем функциям.</p>
            <a href="login.php" class="btn-white"><i class="fas fa-sign-in-alt"></i> Войти в аккаунт</a>
            <a href="login.php#register" class="btn-outline"><i class="fas fa-user-plus"></i> Зарегистрироваться</a>
        </div>
        <?php endif; ?>
    </div>
</main>

<?= renderFooter() ?>

<!-- AUTH MODAL -->
<div class="modal-overlay" id="authModal" onclick="if(event.target===this)closeAuth()">
    <div class="auth-modal">
        <div class="auth-modal-top">
            <div class="auth-lock"><i class="fas fa-lock"></i></div>
            <h3>Требуется авторизация</h3>
            <p>Эта функция доступна только зарегистрированным пользователям</p>
        </div>
        <div class="auth-modal-body">
            <p>Войдите в аккаунт или создайте его, чтобы пользоваться всеми услугами ДомУчета.</p>
            <a href="login.php" class="auth-btn-primary"><i class="fas fa-sign-in-alt"></i> Войти в аккаунт</a>
            <a href="login.php#register" class="auth-btn-secondary"><i class="fas fa-user-plus"></i> Зарегистрироваться</a>
            <span class="modal-cancel" onclick="closeAuth()">Отмена</span>
        </div>
    </div>
</div>

<!-- ARTICLE MODAL -->
<div class="modal-overlay" id="articleModal" onclick="if(event.target===this)closeArticle()">
    <div class="article-modal">
        <div class="article-modal-top" id="articleTop" style="position:relative;">
            <button class="article-close" onclick="closeArticle()"><i class="fas fa-times"></i></button>
            <div class="article-modal-icon" id="articleIcon"></div>
            <h2 id="articleTitle"></h2>
        </div>
        <div class="article-modal-body" id="articleBody"></div>
    </div>
</div>

<script>
const articles = {
    a1:{icon:'🏙️',svg:'<svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="12" y="28" width="56" height="44" rx="4" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/><path d="M12 38 L40 18 L68 38" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round"/><rect x="28" y="44" width="10" height="10" rx="2" fill="#93C5FD"/><rect x="42" y="44" width="10" height="10" rx="2" fill="#93C5FD"/><rect x="32" y="58" width="16" height="14" rx="2" fill="#2C6DB5"/><circle cx="40" cy="65" r="1.5" fill="white"/><rect x="20" y="30" width="6" height="8" rx="1.5" fill="#BFDBFE"/></svg>',title:'Общее имущество дома',html:'<h3>Что относится к общему имуществу</h3><p>Общее имущество МКД — всё, чем жильцы пользуются совместно: подъезды, лифты, лестничные клетки, чердаки, подвалы, инженерные сети. Собственники управляют им совместно и несут ответственность.</p><ul><li>Понимайте состав общего имущества вашего дома</li><li>Фиксируйте проблемы и участвуйте в собраниях</li><li>Инициируйте необходимые работы по содержанию и ремонту</li></ul><h3>Зачем это знать</h3><p>Понимание помогает правильно распределять расходы, планировать ремонты и контролировать управляющую организацию.</p>'},
    a2:{icon:'💼',svg:'<svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="18" y="20" width="44" height="52" rx="4" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/><rect x="26" y="32" width="28" height="3" rx="1.5" fill="#93C5FD"/><rect x="26" y="40" width="20" height="3" rx="1.5" fill="#93C5FD"/><rect x="26" y="48" width="24" height="3" rx="1.5" fill="#93C5FD"/><circle cx="54" cy="56" r="10" fill="#2C6DB5"/><path d="M49 56 L53 60 L60 52" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="26" y="22" width="16" height="6" rx="1.5" fill="#2C6DB5"/></svg>',title:'Что делает управляющая организация',html:'<h3>Основные обязанности УК</h3><ul><li>Содержание и текущий ремонт общего имущества</li><li>Обеспечение бесперебойной работы инженерных систем</li><li>Организация уборки и вывоз мусора</li><li>Ведение расчётов и выставление квитанций</li><li>Работа с обращениями и заявками жильцов</li></ul><h3>Как контролировать УК</h3><p>Изучите договор управления, фиксируйте все обращения, участвуйте в собраниях. При необходимости жильцы могут сменить УК.</p>'},
    a3:{icon:'📋',svg:'<svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10 70 L40 14 L70 70 Z" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5" stroke-linejoin="round"/><rect x="33" y="52" width="14" height="18" rx="2" fill="#2C6DB5"/><circle cx="40" cy="62" r="1.5" fill="white"/><rect x="22" y="56" width="8" height="8" rx="2" fill="#93C5FD"/><rect x="50" y="56" width="8" height="8" rx="2" fill="#93C5FD"/><path d="M34 38 L40 28 L46 38" stroke="#F97316" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><rect x="36" y="42" width="8" height="2.5" rx="1.25" fill="#F97316"/></svg>',title:'Какие работы входят в капитальный ремонт',html:'<h3>Типовые виды работ</h3><ul><li>Ремонт или замена кровли и фасада</li><li>Замена инженерных сетей (водоснабжение, отопление, канализация)</li><li>Ремонт или замена лифтов</li><li>Укрепление конструкций здания</li><li>Модернизация электрооборудования</li></ul><h3>Откуда берутся средства</h3><p>Капремонт финансируется за счёт взносов собственников на общий или специальный счёт. Следите за программой капремонта для вашего дома.</p>'},
    a4:{icon:'⭐',svg:'<svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="40" cy="40" r="26" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/><path d="M40 22 L43.5 33 L55 33 L45.8 39.5 L49 51 L40 44.5 L31 51 L34.2 39.5 L25 33 L36.5 33 Z" fill="#F5C518" stroke="#E6A800" stroke-width="1.5" stroke-linejoin="round"/></svg>',title:'Качество жилищно-коммунальных услуг',html:'<h3>Признаки некачественных услуг</h3><ul><li>Частые перебои с водой, отоплением, электричеством</li><li>Грязные подъезды, неубранная территория</li><li>Несвоевременный вывоз мусора</li><li>Отсутствие реакции на заявки и обращения</li></ul><h3>Как действовать</h3><p>Фиксируйте нарушения, подавайте письменные обращения в УК, при необходимости обращайтесь в жилищную инспекцию или Роспотребнадзор.</p>'},
    a5:{icon:'🔧',svg:'<svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="14" y="36" width="52" height="34" rx="4" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/><path d="M14 44 L40 22 L66 44" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round"/><circle cx="57" cy="26" r="8" fill="#F97316"/><path d="M53 26 L56 29 L62 22" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><rect x="24" y="50" width="10" height="10" rx="2" fill="#93C5FD"/><rect x="46" y="50" width="10" height="10" rx="2" fill="#93C5FD"/><rect x="35" y="54" width="10" height="16" rx="2" fill="#2C6DB5"/></svg>',title:'Планируем капремонт',html:'<h3>Участие собственников</h3><p>Собственники могут вносить предложения по видам работ, срокам и приоритетам, обсуждать их на общем собрании и принимать решения голосованием.</p><h3>Что важно учесть</h3><ul><li>Техническое состояние дома и конструкций</li><li>Наличие аварийных участков и скрытых проблем</li><li>Финансовые возможности собственников</li><li>Перспективы модернизации (энергоэффективность)</li></ul>'},
    a6:{icon:'👥',svg:'<svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="28" cy="28" r="10" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/><circle cx="52" cy="28" r="10" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/><path d="M14 62 C14 50 22 44 28 44 C34 44 38 47 40 50 C42 47 46 44 52 44 C58 44 66 50 66 62" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round" fill="#EEF4FF"/><path d="M33 28 L37 32 L43 24" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',title:'Общее собрание собственников',html:'<h3>Как провести собрание</h3><ul><li>Определите повестку и подготовьте проекты решений</li><li>Заблаговременно уведомьте всех собственников</li><li>Зафиксируйте кворум и результаты голосования</li><li>Оформите протокол по установленной форме</li></ul><h3>Почему это важно</h3><p>Через общее собрание жильцы принимают ключевые решения: выбирают способ управления, утверждают тарифы и планы работ.</p>'},
    a7:{icon:'🏦',svg:'<svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="40" cy="52" rx="24" ry="12" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/><ellipse cx="40" cy="44" rx="24" ry="12" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/><ellipse cx="40" cy="36" rx="24" ry="12" fill="#DBEAFE" stroke="#2C6DB5" stroke-width="2.5"/><path d="M37 32 L37 40 M43 32 L43 40" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round"/><path d="M34 36 C34 34.3 36.7 33 40 33 C43.3 33 46 34.3 46 36" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round"/></svg>',title:'Спецсчёт для капремонта',html:'<h3>Преимущества спецсчёта</h3><ul><li>Деньги накапливаются только для вашего дома</li><li>Жильцы сами определяют сроки и виды работ</li><li>Больше прозрачности и контроля за расходами</li></ul><h3>Как открыть спецсчёт</h3><p>Решение принимается на общем собрании собственников. После этого выбирается банк и оформляются необходимые документы.</p>'},
    a8:{icon:'✉️',svg:'<svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="12" y="24" width="46" height="34" rx="4" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5"/><path d="M12 30 L35 46 L58 30" stroke="#2C6DB5" stroke-width="2.5" stroke-linecap="round"/><circle cx="58" cy="54" r="12" fill="#F97316"/><path d="M54 54 L57 57 L63 50" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',title:'Как повлиять на управляющую организацию',html:'<h3>Работаем по шагам</h3><ul><li>Фиксируем проблему и обращаемся в УК</li><li>При отсутствии реакции подаём коллективные жалобы</li><li>Подключаем контролирующие органы</li><li>При необходимости инициируем смену УК</li></ul><h3>Роль онлайн-сервиса</h3><p>Через личный кабинет вы можете направлять обращения, отслеживать статусы заявок и видеть историю работы УК с вашим домом.</p>'}
};

const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

// SVG иконки для результатов поиска
const searchIcons = {
    meters: `<svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="18" cy="18" r="10" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="1.5"/>
        <circle cx="18" cy="18" r="6" fill="white" stroke="#93C5FD" stroke-width="1"/>
        <path d="M18 13 L18 18 L22 20" stroke="#EF4444" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="18" cy="18" r="1.2" fill="#2C6DB5"/>
        <path d="M12 18 L13 18 M23 18 L24 18 M18 12 L18 13 M18 23 L18 24" stroke="#2C6DB5" stroke-width="1.2" stroke-linecap="round"/>
        <path d="M15 9 C15 7.5 16.5 6 16.5 6 C16.5 6 18 7.5 18 9 C18 10.2 17 11 16.5 11 C16 11 15 10.2 15 9Z" fill="#93C5FD" stroke="#2C6DB5" stroke-width="1"/>
    </svg>`,
    invoices: `<svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="9" y="6" width="18" height="24" rx="2" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="1.5"/>
        <rect x="12" y="11" width="11" height="1.5" rx="0.75" fill="#93C5FD"/>
        <rect x="12" y="14.5" width="8" height="1.5" rx="0.75" fill="#93C5FD"/>
        <rect x="12" y="18" width="9" height="1.5" rx="0.75" fill="#93C5FD"/>
        <circle cx="24" cy="25" r="6.5" fill="#EF4444"/>
        <text x="24" y="28" text-anchor="middle" font-size="7.5" font-weight="bold" fill="white" font-family="Arial">₽</text>
        <path d="M9 27 L6 30 L9 30 Z" fill="#1a4f8a"/>
    </svg>`,
    incidents: `<svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="17" cy="17" r="11" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="1.5"/>
        <path d="M13 21 L21 13" stroke="#2C6DB5" stroke-width="1.8" stroke-linecap="round"/>
        <circle cx="12" cy="22" r="2.5" fill="none" stroke="#2C6DB5" stroke-width="1.5"/>
        <circle cx="22" cy="12" r="2.5" fill="none" stroke="#2C6DB5" stroke-width="1.5"/>
        <circle cx="27" cy="9" r="5" fill="#EF4444"/>
        <rect x="26.3" y="6.5" width="1.4" height="3" rx="0.7" fill="white"/>
        <circle cx="27" cy="11" r="0.8" fill="white"/>
    </svg>`,
    profile: `<svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="16" cy="13" r="7" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="1.5"/>
        <path d="M7 32 C7 24 11 20 16 20 C21 20 25 24 25 32" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="1.5" stroke-linecap="round"/>
        <rect x="23" y="21" width="10" height="8" rx="1.8" fill="#EF4444" stroke="#c91c1c" stroke-width="1"/>
        <path d="M25 21 L25 19 C25 17 31 17 31 19 L31 21" stroke="#2C6DB5" stroke-width="1.5" stroke-linecap="round" fill="none"/>
        <circle cx="28" cy="25" r="1.5" fill="white"/>
    </svg>`,
    article: `<svg viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M18 28 C18 28 10 23 7 9 L18 11 L29 9 C26 23 18 28 18 28Z" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="1.5" stroke-linejoin="round"/>
        <line x1="18" y1="11" x2="18" y2="28" stroke="#2C6DB5" stroke-width="1.2" stroke-dasharray="2 1.5"/>
        <rect x="11" y="14" width="6" height="1.2" rx="0.6" fill="#93C5FD"/>
        <rect x="11" y="16.5" width="5" height="1.2" rx="0.6" fill="#93C5FD"/>
        <rect x="11" y="19" width="5.5" height="1.2" rx="0.6" fill="#93C5FD"/>
        <rect x="20" y="14" width="6" height="1.2" rx="0.6" fill="#EF4444" opacity="0.7"/>
        <rect x="20" y="16.5" width="5" height="1.2" rx="0.6" fill="#EF4444" opacity="0.7"/>
        <rect x="20" y="19" width="5.5" height="1.2" rx="0.6" fill="#EF4444" opacity="0.7"/>
    </svg>`
};

const searchIndex = [
    {q:['счётчик','показани','воды','электр','газ'],title:'Передать показания',icon:'meters',href:'meters.php',locked:!isLoggedIn},
    {q:['квитанц','оплатить','платёж','платеж','долг'],title:'Оплатить квитанции',icon:'invoices',href:'invoices.php',locked:!isLoggedIn},
    {q:['заявк','ремонт','поломк','протечк'],title:'Создать заявку',icon:'incidents',href:'profile.php',locked:!isLoggedIn},
    {q:['профил','кабинет','аккаунт'],title:'Личный кабинет',icon:'profile',href:'profile.php',locked:!isLoggedIn},
    {q:['общее имущество','имуществ'],title:'Статья: Общее имущество дома',icon:'article',article:'a1'},
    {q:['управляющ','организац'],title:'Статья: Управляющая организация',icon:'article',article:'a2'},
    {q:['капремонт','капитальный'],title:'Статья: Капитальный ремонт',icon:'article',article:'a3'},
    {q:['качество','услуги жкх'],title:'Статья: Качество услуг',icon:'article',article:'a4'},
    {q:['собрание','собственник'],title:'Статья: Общее собрание',icon:'article',article:'a6'},
    {q:['спецсчёт','спецсчет'],title:'Статья: Спецсчёт',icon:'article',article:'a7'},
];

function requireAuth(e){
    e.preventDefault();
    document.getElementById('authModal').classList.add('active');
    document.body.style.overflow='hidden';
    return false;
}
function closeAuth(){
    document.getElementById('authModal').classList.remove('active');
    document.body.style.overflow='';
}
function openArticle(id){
    const a=articles[id];if(!a)return;
    document.getElementById('articleIcon').innerHTML=a.svg||a.icon;
    document.getElementById('articleTitle').textContent=a.title;
    document.getElementById('articleBody').innerHTML=a.html;
    document.getElementById('articleModal').classList.add('active');
    document.body.style.overflow='hidden';
}
function closeArticle(){
    document.getElementById('articleModal').classList.remove('active');
    document.body.style.overflow='';
}
function setSearch(val){
    document.getElementById('searchInput').value=val;
    doSearch();
}
function doSearch(){
    const q=document.getElementById('searchInput').value.toLowerCase().trim();
    const container=document.getElementById('search-results');
    const list=document.getElementById('sr-list');
    if(!q||q.length<2){container.style.display='none';if(window.catSetIdle)catSetIdle();return;}
    if(window.catSetSearching)catSetSearching();
    setTimeout(()=>{
        const results=searchIndex.filter(item=>item.q.some(k=>q.includes(k)||k.includes(q)));
        if(!results.length){
            list.innerHTML='<p style="color:rgba(255,255,255,.7);font-size:0.85rem;padding:0.5rem 0;">По запросу ничего не найдено. Попробуйте другое слово.</p>';
        } else {
            list.innerHTML=results.map(r=>{
                const lockIcon=r.locked?' <i class="fas fa-lock" style="color:#9DAABF;font-size:0.75rem;"></i>':'';
                const iconSvg = searchIcons[r.icon] || '';
                if(r.article){
                    return `<div class="sr-item" onclick="closeSearch();openArticle('${r.article}')">
                        <div class="sr-icon">${iconSvg}</div>
                        <div><div class="sr-title">${r.title}</div><div class="sr-sub">Статья — доступна всем</div></div></div>`;
                } else if(r.locked){
                    return `<div class="sr-item" onclick="requireAuth(event)">
                        <div class="sr-icon">${iconSvg}</div>
                        <div><div class="sr-title">${r.title}${lockIcon}</div><div class="sr-sub">Требуется авторизация</div></div></div>`;
                } else {
                    return `<a class="sr-item" href="${r.href}">
                        <div class="sr-icon">${iconSvg}</div>
                        <div><div class="sr-title">${r.title}</div><div class="sr-sub">Услуга</div></div></a>`;
                }
            }).join('');
        }
        container.style.display='block';
        if(window.catSetFound)catSetFound(results.length);
    }, 600);
}
function closeSearch(){
    document.getElementById('search-results').style.display='none';
    document.getElementById('searchInput').value='';
    if(window.catSetIdle)catSetIdle();
}
document.getElementById('searchInput').addEventListener('keydown',e=>{if(e.key==='Enter')doSearch();});
document.getElementById('searchInput').addEventListener('input',()=>{if(!document.getElementById('searchInput').value.trim())document.getElementById('search-results').style.display='none';});
</script>
<!-- ── CHAT FULLSCREEN ── -->
<div id="catChat">
    <!-- звёзды -->
    <div class="chat-stars" id="chatStars"></div>

    <!-- название -->
    <div class="chat-topbar">
        <div class="chat-topbar-dot"></div>
        УМКА — помощник ДомУчет
    </div>

    <!-- кнопка закрыть -->
    <button class="chat-close-btn" onclick="closeChat()"><i class="fas fa-times"></i></button>

    <!-- персонаж слева -->
    <div class="chat-mascot-area">
        <canvas id="chatMascotCanvas" width="20" height="24"></canvas>
    </div>

    <!-- центральная колонка -->
    <div class="chat-main">
        <div class="chat-messages" id="chatMessages">
            <div class="chat-msg bot">
                <div class="chat-bubble">
                    Привет! Я УМКА 🐱<br><br>
                    Помогу разобраться с показаниями счётчиков, квитанциями, заявками на ремонт и другими вопросами по сайту ДомУчет.<br><br>
                    Напишите свой вопрос!
                </div>
            </div>
        </div>
        <div class="chat-input-row">
            <input type="text" id="chatInput" placeholder="Введите запрос..." maxlength="500" autocomplete="off">
            <button class="chat-send-btn" id="chatSend" onclick="sendChatMessage()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>
</div>

<script src="assets/js/pixelcat.js"></script>
</body>
</html>
