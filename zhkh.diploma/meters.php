<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/layout.php';
// Читаем ключ Gemini из отдельного файла
if (!defined('GEMINI_API_KEY')) {
    $geminiKeyFile = __DIR__ . '/gemini_key.php';
    if (file_exists($geminiKeyFile)) require_once $geminiKeyFile;
}
initFlashFromCookie();
requireLogin();

$user = getCurrentUser();
if (!$user) { redirect('login.php'); }
$db   = db();
$success = '';
$error   = '';

$stmt = $db->prepare("SELECT * FROM apartments WHERE owner_user_id = ?");
$stmt->execute([$user['user_id']]);
$apartment = $stmt->fetch();
if (!$apartment && !hasRole('admin')) $error = 'Квартира не привязана к вашему аккаунту. Обратитесь к администратору.';

// Сохранение показаний
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $apartment && isset($_POST['submit_readings'])) {
    $readingDate  = $_POST['reading_date'];
    $readingMonth = date('Y-m', strtotime($readingDate));
    try {
        $db->beginTransaction();
        $stmt = $db->query("SELECT * FROM meter_types ORDER BY meter_type_id");
        $allTypes = $stmt->fetchAll();

        // Сначала сохраняем все показания
        foreach ($allTypes as $type) {
            $fn = 'meter_' . $type['meter_type_id'];
            if (isset($_POST[$fn]) && $_POST[$fn] !== '') {
                $stmt = $db->prepare("INSERT INTO meter_readings
                    (apartment_id, meter_type_id, reading_value, reading_date, reading_month, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE reading_value=VALUES(reading_value), reading_date=VALUES(reading_date)");
                $stmt->execute([$apartment['apartment_id'], $type['meter_type_id'], floatval($_POST[$fn]), $readingDate, $readingMonth, $user['user_id']]);
            }
        }

        // Фото: каждый файл из photos[] привязываем к соответствующему счётчику по порядку типов
        // Поля input имеют name="photos[]" — они приходят в том порядке, в котором
        // расположены на странице (горячая, газ, электро, тепло).
        // Привязываем фото к конкретному meter_type_id по индексу.
        if (!empty($_FILES['photos']['name'][0])) {
            $allPhotos = []; // все пути для общего хранения
            foreach ($_FILES['photos']['name'] as $k => $n) {
                if (empty($n) || $_FILES['photos']['error'][$k] !== UPLOAD_ERR_OK) continue;
                $r = uploadFile([
                    'name'     => $n,
                    'type'     => $_FILES['photos']['type'][$k],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$k],
                    'error'    => $_FILES['photos']['error'][$k],
                    'size'     => $_FILES['photos']['size'][$k],
                ], 'meters');
                if (!$r['success']) continue;
                $allPhotos[] = $r['path'];

                // Привязываем к счётчику по индексу: k=0 → первый тип, k=1 → второй и т.д.
                if (isset($allTypes[$k])) {
                    $tid = $allTypes[$k]['meter_type_id'];
                    $stmtP = $db->prepare("UPDATE meter_readings SET photos_json=? WHERE apartment_id=? AND meter_type_id=? AND reading_month=?");
                    $stmtP->execute([json_encode([$r['path']]), $apartment['apartment_id'], $tid, $readingMonth]);
                }
            }
            // Также сохраняем все фото в первую запись месяца (для обратной совместимости)
            if ($allPhotos) {
                $stmtG = $db->prepare("UPDATE meter_readings SET photos_json=? WHERE apartment_id=? AND reading_month=? LIMIT 1");
                $stmtG->execute([json_encode($allPhotos), $apartment['apartment_id'], $readingMonth]);
            }
        }
        $db->commit();
        $success = 'Показания успешно сохранены!';
        sendNotification($user['user_id'], 'Показания приняты', 'Показания за ' . date('m.Y', strtotime($readingDate)) . ' приняты', 'success', 'meters.php');
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Ошибка: ' . $e->getMessage();
    }
}

// История показаний
$readingsByMonth = [];
if ($apartment) {
    $stmt = $db->prepare("SELECT mr.*, mt.type_name, mt.unit FROM meter_readings mr JOIN meter_types mt ON mr.meter_type_id = mt.meter_type_id WHERE mr.apartment_id = ? ORDER BY mr.reading_date DESC");
    $stmt->execute([$apartment['apartment_id']]);
    foreach ($stmt->fetchAll() as $r) {
        $m = $r['reading_month'];
        if (!isset($readingsByMonth[$m])) $readingsByMonth[$m] = ['date'=>$r['reading_date'],'status'=>$r['status'],'photos'=>$r['photos_json'],'meters'=>[]];
        $readingsByMonth[$m]['meters'][$r['type_name']] = ['value'=>$r['reading_value'],'unit'=>$r['unit'],'difference'=>$r['difference']];
    }
}

$stmt = $db->query("SELECT * FROM meter_types ORDER BY meter_type_id");
$meterTypes = $stmt->fetchAll();

$lastReadings = [];
if ($apartment) {
    foreach ($meterTypes as $type) {
        $stmt = $db->prepare("SELECT reading_value FROM meter_readings WHERE apartment_id=? AND meter_type_id=? ORDER BY reading_date DESC LIMIT 1");
        $stmt->execute([$apartment['apartment_id'], $type['meter_type_id']]);
        $last = $stmt->fetch();
        $lastReadings[$type['meter_type_id']] = $last ? $last['reading_value'] : 0;
    }
}

$meterColors = ['c1','c2','c3','c4'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Счётчики — ДомУчет</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=PT+Sans:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?= pageStyles() ?>
    <style>
        .meter-card-icon-svg { width: 56px; height: 56px; margin-bottom: .75rem; }
        .meter-card-icon-svg svg { width: 56px; height: 56px; }
        .meter-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.75rem; }
    </style>
</head>
<body>
<?= renderHeader($user, 'meters') ?>

<section class="page-hero">
    <div class="page-hero-inner">
        <h1><svg viewBox="0 0 28 28" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;flex-shrink:0"><circle cx="14" cy="14" r="10"/><path d="M14 8v6l4 2"/><circle cx="14" cy="14" r="1.5" fill="white" stroke="none"/></svg> Счётчики</h1>
        <p>Передача и история показаний<?= $apartment ? ' — Квартира №' . escape($apartment['apartment_number']) : '' ?></p>
    </div>
</section>

<main class="main">
    <?php if ($success): ?><div class="alert alert-success"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px"><circle cx="10" cy="10" r="8"/><path d="M6 10l3 3 5-6"/></svg> <?= escape($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="width:18px;height:18px"><circle cx="10" cy="10" r="8"/><path d="M10 6v5M10 14v1"/></svg> <?= escape($error) ?></div><?php endif; ?>

    <?php if (hasRole('admin') && !$apartment): ?>
    <div style="background:#EEF4FF;border:1px solid #93C5FD;border-radius:12px;padding:1.5rem;text-align:center;margin-top:1rem;">
        <i class="fas fa-shield-alt" style="font-size:2.5rem;color:#2C6DB5;margin-bottom:1rem;display:block;"></i>
        <h3 style="color:#1A2540;margin-bottom:.5rem;">Вы вошли как администратор</h3>
        <p style="color:#475569;font-size:.9rem;margin-bottom:1.25rem;">Показания счётчиков всех жильцов доступны в панели администратора.</p>
        <a href="admin/index.php?tab=readings" style="background:#2C6DB5;color:#fff;padding:.65rem 1.5rem;border-radius:10px;text-decoration:none;font-weight:600;font-size:.9rem;">
            <i class="fas fa-tachometer-alt"></i> Открыть показания в панели
        </a>
    </div>
    <?php elseif ($apartment): ?>

    <!-- Последние показания -->
    <?php if (array_filter($lastReadings)): ?>
    <div class="meter-grid">
        <?php foreach ($meterTypes as $i => $type): ?>
        <div class="meter-card">
            <?php
            $mSvgs = [
                'fa-tint' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M24 6C24 6 10 20 10 30a14 14 0 0028 0C38 20 24 6 24 6z" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2.5" stroke-linejoin="round"/><path d="M16 32c0 4.4 3.6 8 8 8" stroke="#93C5FD" stroke-width="2.5" stroke-linecap="round"/><path d="M14 36c1 1 2 1.5 3 2" stroke="#93C5FD" stroke-width="2" stroke-linecap="round"/></svg>',
                'fa-fire' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M24 6C24 6 10 20 10 30a14 14 0 0028 0C38 20 24 6 24 6z" fill="#FEF3C7" stroke="#F97316" stroke-width="2.5" stroke-linejoin="round"/><path d="M22 20c0 0-4 4-2 8 1 2 3 3 5 3s5-2 5-5c0-2-1-3-2-4 0 2-1 3-2 3s-2-1-2-3c0-1 0-1.5 0-2z" fill="#F97316" stroke="none"/><path d="M16 34c0 3 3.6 6 8 6" stroke="#FDBA74" stroke-width="2" stroke-linecap="round"/></svg>',
                'fa-bolt' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="24" r="18" fill="#FEF9C3" stroke="#F5C518" stroke-width="2.5"/><path d="M27 10L16 26h10L21 40l14-18H24L27 10z" fill="#F5C518" stroke="#D97706" stroke-width="1.5" stroke-linejoin="round"/></svg>',
                'fa-temperature-high' => '<svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="8" y="14" width="32" height="24" rx="4" fill="#FEE2E2" stroke="#EF4444" stroke-width="2.5"/><line x1="16" y1="14" x2="16" y2="38" stroke="#EF4444" stroke-width="2"/><line x1="24" y1="14" x2="24" y2="38" stroke="#EF4444" stroke-width="2"/><line x1="32" y1="14" x2="32" y2="38" stroke="#EF4444" stroke-width="2"/><path d="M14 8c0 0 2 2 2 4s-2 4-2 4" stroke="#F97316" stroke-width="2" stroke-linecap="round"/><path d="M22 6c0 0 2 3 2 5s-2 5-2 5" stroke="#F97316" stroke-width="2" stroke-linecap="round"/><path d="M30 8c0 0 2 2 2 4s-2 4-2 4" stroke="#F97316" stroke-width="2" stroke-linecap="round"/></svg>',
            ];
            $ic = $type['icon_class'];
            ?>
            <div class="meter-card-icon-svg"><?= $mSvgs[$ic] ?? '<i class="fas '.$ic.'"></i>' ?></div>
            <div class="meter-card-label"><?= escape($type['type_name']) ?></div>
            <div class="meter-card-value"><?= number_format($lastReadings[$type['meter_type_id']], 2, '.', ' ') ?> <span class="meter-card-unit"><?= escape($type['unit']) ?></span></div>
            <div class="meter-card-prev">Последнее показание</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Форма -->
    <div class="card">
        <div class="card-title">
            <svg viewBox="0 0 22 22" fill="none" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M11 4H4a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Передать показания за <?= date('F Y') ?>
        </div>

        <form method="POST" enctype="multipart/form-data">

            <!-- Дата снятия -->
            <div class="form-group" style="max-width:260px;margin-bottom:1.75rem;">
                <label><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:15px;height:15px"><rect x="2" y="3" width="16" height="15" rx="2"/><path d="M2 8h16M7 1v4M13 1v4"/></svg> Дата снятия</label>
                <input type="date" name="reading_date" value="<?= date('Y-m-d') ?>" required>
            </div>

            <!-- УМКА: статус -->
            <div id="umkaStatusWrap" style="display:none;margin-bottom:1rem;">
                <div id="umkaStatus" class="umka-status">
                    <span class="ai-spinner"></span>
                    <span id="umkaStatusText">УМКА читает показания...</span>
                </div>
            </div>

            <!-- Карточки счётчиков с полями ввода -->
            <div class="meter-form-grid" style="margin-top:1.5rem;">
            <?php
            $lblSvgs = [
                'fa-tint'            => '<svg viewBox="0 0 18 18" fill="none" stroke="#2C6DB5" stroke-width="1.8" stroke-linecap="round" style="width:18px;height:18px;flex-shrink:0"><path d="M9 2C9 2 3 8 3 12a6 6 0 0012 0C15 8 9 2 9 2z"/></svg>',
                'fa-fire'            => '<svg viewBox="0 0 18 18" fill="none" stroke="#F97316" stroke-width="1.8" stroke-linecap="round" style="width:18px;height:18px;flex-shrink:0"><path d="M9 2c0 0-5 5-5 9a5 5 0 0010 0C14 7 9 2 9 2z"/><path d="M8 9c0 0-1 2 0 3.5"/></svg>',
                'fa-bolt'            => '<svg viewBox="0 0 18 18" fill="none" stroke="#D97706" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;flex-shrink:0"><path d="M10 2L5 10h5l-2 6 8-9H11L10 2z"/></svg>',
                'fa-temperature-high'=> '<svg viewBox="0 0 18 18" fill="none" stroke="#EF4444" stroke-width="1.8" stroke-linecap="round" style="width:18px;height:18px;flex-shrink:0"><rect x="3" y="5" width="12" height="9" rx="2"/><line x1="6" y1="5" x2="6" y2="14"/><line x1="9" y1="5" x2="9" y2="14"/><line x1="12" y1="5" x2="12" y2="14"/></svg>',
            ];
            $meterAccentColors = [
                'fa-tint'            => '#2C6DB5',
                'fa-fire'            => '#F97316',
                'fa-bolt'            => '#D97706',
                'fa-temperature-high'=> '#EF4444',
            ];
            foreach ($meterTypes as $i => $type):
                $tid    = $type['meter_type_id'];
                $ic     = $type['icon_class'];
                $accent = $meterAccentColors[$ic] ?? '#2C6DB5';
                $svg    = $lblSvgs[$ic] ?? '';
                $prev   = $lastReadings[$tid] ?? 0;
            ?>
            <div class="meter-form-card" id="mcard_<?= $tid ?>" style="--accent:<?= $accent ?>;">
                <div class="mfc-header">
                    <span class="mfc-icon"><?= $svg ?></span>
                    <span class="mfc-title"><?= escape($type['type_name']) ?></span>
                    <span class="mfc-unit"><?= escape($type['unit']) ?></span>
                    <!-- Миниатюра найденного фото -->
                    <div id="mcard_thumb_<?= $tid ?>" class="mfc-thumb" style="display:none;"></div>
                </div>

                <div class="form-group" style="margin-bottom:.5rem;">
                    <input type="number"
                           id="field_meter_<?= $tid ?>"
                           name="meter_<?= $tid ?>"
                           step="0.01"
                           placeholder="Текущее показание"
                           min="<?= $prev ?>">
                    <small>Предыдущее: <strong><?= number_format($prev, 2, '.', ' ') ?></strong> <?= escape($type['unit']) ?></small>
                </div>

                <!-- Зона загрузки фото для ЭТОГО счётчика -->
                <div class="mfc-upload-zone" id="mfc_zone_<?= $tid ?>"
                     onclick="document.getElementById('mfc_input_<?= $tid ?>').click()"
                     ondragover="event.preventDefault();this.classList.add('dragover')"
                     ondragleave="this.classList.remove('dragover')"
                     ondrop="handleMfcDrop(event,<?= $tid ?>)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--accent,#2C6DB5)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:22px;height:22px;flex-shrink:0"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                    <span>Фото счётчика — УМКА прочитает цифры</span>
                </div>
                <input type="file" id="mfc_input_<?= $tid ?>" name="photos[]" accept="image/*" style="display:none;"
                       onchange="handleMfcPhoto(this,<?= $tid ?>,'<?= addslashes($type['type_name']) ?>')">

                <!-- Статус и превью -->
                <div id="mcard_status_<?= $tid ?>" class="mfc-ai-badge" style="display:none;"></div>
                <div id="mcard_bigphoto_<?= $tid ?>" class="mfc-bigphoto" style="display:none;"></div>
            </div>
            <?php endforeach; ?>
            </div>

            <div class="btn-row" style="margin-top:1.75rem;">
                <button type="submit" name="submit_readings" class="btn-primary">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M17 21H3a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M13 3v5H7V3M7 13h6"/></svg>
                    Сохранить
                </button>
                <button type="button" class="btn-secondary" onclick="resetUmka()">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
                    Сбросить
                </button>
            </div>
        </form>
    </div>

    <!-- История -->
    <div class="card">
        <div class="card-title"><svg viewBox="0 0 22 22" fill="none" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><circle cx="11" cy="11" r="9"/><path d="M11 6v5l3 2"/><path d="M3.5 3.5l1.8 1.8"/></svg> История показаний</div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Период</th>
                        <th>Дата</th>
                        <?php foreach ($meterTypes as $t): ?><th><?= escape($t['type_name']) ?></th><?php endforeach; ?>
                        <th>Статус</th>
                        <th>Фото</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($readingsByMonth)): ?>
                        <tr><td colspan="<?= count($meterTypes)+4 ?>" style="text-align:center;padding:2rem;color:var(--gray-400);">Нет данных о показаниях</td></tr>
                    <?php else: ?>
                        <?php foreach ($readingsByMonth as $month => $data): ?>
                        <tr>
                            <td><strong><?= date('m.Y', strtotime($month)) ?></strong></td>
                            <td><?= formatDate($data['date']) ?></td>
                            <?php foreach ($meterTypes as $t): ?>
                            <td>
                                <?php if (isset($data['meters'][$t['type_name']])): $m = $data['meters'][$t['type_name']]; ?>
                                    <strong><?= number_format($m['value'], 2, '.', ' ') ?></strong> <span style="color:var(--gray-400);font-size:.8rem;"><?= $m['unit'] ?></span>
                                    <?php if ($m['difference']): ?><br><span style="font-size:.78rem;color:var(--gray-600);">расход: <?= number_format($m['difference'], 2) ?></span><?php endif; ?>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                            <td>
                                <?php
                                $sBadge = ['pending'=>'badge-pending','approved'=>'badge-approved','rejected'=>'badge-rejected'];
                                $sText  = ['pending'=>'Ожидает','approved'=>'Принято','rejected'=>'Отклонено'];
                                ?>
                                <span class="badge <?= $sBadge[$data['status']] ?>"><?= $sText[$data['status']] ?></span>
                            </td>
                            <td>
                                <?php if ($data['photos']): $ph = json_decode($data['photos'], true); ?>
                                    <span class="badge badge-new"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="width:13px;height:13px"><rect x="2" y="3" width="12" height="10" rx="2"/><circle cx="6" cy="7" r="1.5"/><path d="M2 11l3-3 3 3 2-2 4 4"/></svg> <?= count($ph) ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php endif; ?>
</main>

<?= renderFooter() ?>

<!-- УМКА использует Яндекс Vision + YandexGPT для всех типов счётчиков -->

<style>
/* ══ УМКА: зона загрузки ══ */
.umka-upload-wrap { margin-bottom:.25rem; }
.umka-upload-zone {
    cursor:pointer; border:2px dashed #C4D4E8; border-radius:14px;
    padding:2rem 1.5rem; text-align:center; transition:all .25s;
    background:linear-gradient(135deg,#f8fbff 0%,#eef4ff 100%);
    position:relative; overflow:hidden;
}
.umka-upload-zone::before {
    content:''; position:absolute; inset:0;
    background:linear-gradient(135deg,rgba(44,109,181,.03) 0%,rgba(44,109,181,.07) 100%);
    opacity:0; transition:opacity .25s;
}
.umka-upload-zone:hover,.umka-upload-zone.dragover {
    border-color:#2C6DB5; border-style:solid;
    box-shadow:0 0 0 4px rgba(44,109,181,.1);
}
.umka-upload-zone:hover::before,.umka-upload-zone.dragover::before { opacity:1; }
.umka-upload-zone.dragover { transform:scale(1.01); }
.umka-zone-inner { display:flex; align-items:center; gap:1.25rem; justify-content:center; }
.umka-icon-wrap { position:relative; flex-shrink:0; }
.umka-sparkle { position:absolute; top:-4px; right:-6px; font-size:1.1rem; animation:sparkle 2s ease-in-out infinite; }
@keyframes sparkle { 0%,100%{transform:scale(1) rotate(0deg);opacity:1;} 50%{transform:scale(1.3) rotate(15deg);opacity:.7;} }
.umka-zone-text { display:flex; flex-direction:column; align-items:flex-start; gap:.2rem; text-align:left; }
.umka-zone-text strong { font-size:1rem; color:#1A2540; }
.umka-zone-text span { font-size:.83rem; color:#64748b; }
.umka-hint { display:inline-block; margin-top:.2rem; font-size:.78rem !important; color:#2C6DB5 !important; background:#EEF4FF; border-radius:20px; padding:.2rem .65rem; font-weight:500; }

/* ══ Статус обработки ══ */
.umka-status { display:flex; align-items:center; gap:.75rem; background:#EEF4FC; border:1px solid #bfdbfe; border-radius:10px; padding:.85rem 1.1rem; font-size:.9rem; color:#1a4f8a; }
.umka-status.us-success { background:#f0fdf4; border-color:#bbf7d0; color:#166534; }
.umka-status.us-error   { background:#fef2f2; border-color:#fecaca; color:#b91c1c; }
.umka-status.us-warn    { background:#fffbeb; border-color:#fde68a; color:#92400e; }
.umka-status .ai-spinner { width:18px; height:18px; border:2.5px solid #bfdbfe; border-top-color:#2C6DB5; border-radius:50%; flex-shrink:0; animation:spin .7s linear infinite; }
.umka-status.us-success .ai-spinner,.umka-status.us-error .ai-spinner,.umka-status.us-warn .ai-spinner { display:none; }
@keyframes spin { to{transform:rotate(360deg);} }

/* Прогресс-бар */
.umka-progress-wrap { position:relative; height:6px; background:#dbeafe; border-radius:6px; margin-top:.6rem; overflow:hidden; }
.umka-progress-bar  { height:100%; background:linear-gradient(90deg,#2C6DB5,#60a5fa); width:0%; transition:width .35s ease; border-radius:6px; }
.umka-progress-label { position:absolute; right:0; top:-18px; font-size:.75rem; color:#64748b; }

/* ══ Лента превью ══ */
.umka-photo-strip { display:flex; flex-wrap:wrap; gap:.6rem; margin-top:.85rem; }
.umka-photo-item { position:relative; width:90px; }
.umka-photo-item img { width:90px; height:70px; object-fit:cover; border-radius:8px; border:2px solid #e2e8f0; display:block; transition:border-color .2s; }
.umka-photo-item.matched img  { border-color:var(--match-color,#2C6DB5); }
.umka-photo-item .photo-label { position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,.55); color:#fff; font-size:.65rem; text-align:center; border-radius:0 0 6px 6px; padding:.15rem .2rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.umka-photo-item .photo-spinner { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,.7); border-radius:7px; }
.umka-photo-item .photo-spinner svg { animation:spin .7s linear infinite; }

/* ══ Карточки счётчиков ══ */
.meter-form-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1rem; }
.meter-form-card { background:#fff; border:2px solid #e2e8f0; border-radius:14px; padding:1.1rem 1.1rem .9rem; display:flex; flex-direction:column; gap:.35rem; transition:border-color .3s,box-shadow .3s,transform .2s; }
.meter-form-card:hover { border-color:var(--accent,#2C6DB5); box-shadow:0 4px 18px rgba(0,0,0,.07); }
.meter-form-card.card-matched { border-color:var(--accent,#2C6DB5); box-shadow:0 0 0 3px color-mix(in srgb,var(--accent) 15%,transparent); }
.meter-form-card.card-matched .mfc-header { animation:cardPop .4s ease; }
@keyframes cardPop { 0%{transform:scale(.97)} 60%{transform:scale(1.02)} 100%{transform:scale(1)} }
.mfc-header { display:flex; align-items:center; gap:.5rem; margin-bottom:.3rem; }
.mfc-icon   { display:flex; align-items:center; justify-content:center; width:34px; height:34px; background:color-mix(in srgb,var(--accent,#2C6DB5) 12%,#fff); border-radius:8px; flex-shrink:0; }
.mfc-title  { font-weight:700; font-size:.9rem; color:#1A2540; flex:1; }
.mfc-unit   { font-size:.72rem; color:#9DAABF; background:#f1f5f9; border-radius:6px; padding:.15rem .45rem; flex-shrink:0; }
.mfc-thumb  { width:36px; height:28px; border-radius:5px; overflow:hidden; flex-shrink:0; border:1.5px solid var(--accent,#2C6DB5); }
.mfc-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
/* Зона загрузки в карточке */
.mfc-upload-zone { display:flex; align-items:center; gap:.5rem; cursor:pointer; border:1.5px dashed color-mix(in srgb,var(--accent,#2C6DB5) 40%,#cdd8e8); border-radius:8px; padding:.55rem .75rem; font-size:.78rem; color:#64748b; transition:all .2s; background:color-mix(in srgb,var(--accent,#2C6DB5) 4%,#fff); margin-top:.3rem; }
.mfc-upload-zone:hover,.mfc-upload-zone.dragover { border-color:var(--accent,#2C6DB5); background:color-mix(in srgb,var(--accent,#2C6DB5) 9%,#fff); color:var(--accent,#2C6DB5); }
/* Большое превью фото в карточке */
.mfc-bigphoto { margin-top:.5rem; border-radius:10px; overflow:hidden; border:1.5px solid var(--accent,#2C6DB5); cursor:zoom-in; position:relative; }
.mfc-bigphoto img { width:100%; max-height:200px; object-fit:contain; background:#f8faff; display:block; transition:max-height .3s; }
.mfc-bigphoto.zoomed { cursor:zoom-out; z-index:10; }
.mfc-bigphoto.zoomed img { max-height:420px; }
.mfc-bigphoto::after { content:'🔍 нажмите для увеличения'; position:absolute; bottom:4px; right:6px; font-size:.65rem; color:#fff; background:rgba(0,0,0,.45); border-radius:4px; padding:.1rem .4rem; pointer-events:none; }
.mfc-bigphoto.zoomed::after { content:'🔍 нажмите для уменьшения'; }
/* Бейдж статуса на карточке */
.mfc-ai-badge { font-size:.78rem; border-radius:8px; padding:.35rem .6rem; display:flex; align-items:center; gap:.35rem; }
.mfc-ai-badge.badge-ok   { background:#f0fdf4; color:#166534; border:1px solid #bbf7d0; }
.mfc-ai-badge.badge-err  { background:#fef2f2; color:#b91c1c; border:1px solid #fecaca; }
/* Анимация заполнения поля */
.ai-filled { animation:highlight .9s ease; }
@keyframes highlight { 0%,100%{background:transparent;} 40%{background:#d1fae5;border-color:#1DB954;box-shadow:0 0 0 3px rgba(29,185,84,.15);} }
</style>


<script>
window._UMKA_KEY = <?= json_encode(defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '') ?>;
</script>

<script>
// ── Данные счётчиков (из PHP) ──
var METER_FIELDS = <?= json_encode(array_map(function($t){
    return ['id'=>$t['meter_type_id'], 'name'=>$t['type_name'], 'unit'=>$t['unit'], 'icon'=>$t['icon_class']];
}, $meterTypes)) ?>;

var ICON_TO_COLOR = { 'fa-tint':'#2C6DB5','fa-fire':'#F97316','fa-bolt':'#D97706','fa-temperature-high':'#EF4444' };

// ── Drag & Drop для карточки ──
function handleMfcDrop(e, tid) {
    e.preventDefault();
    document.getElementById('mfc_zone_' + tid).classList.remove('dragover');
    var files = Array.from(e.dataTransfer.files).filter(function(f){ return f.type.startsWith('image/'); });
    if (!files.length) return;
    var inp = document.getElementById('mfc_input_' + tid);
    var dt = new DataTransfer(); dt.items.add(files[0]); inp.files = dt.files;
    var mf = METER_FIELDS.find(function(f){ return f.id == tid; });
    handleMfcPhoto(inp, tid, mf ? mf.name : '');
}

// ── Обработка загрузки фото для конкретного счётчика ──
function handleMfcPhoto(input, tid, meterName) {
    if (!input.files.length) return;
    var file = input.files[0];

    // Показываем превью сразу
    var bigPhoto = document.getElementById('mcard_bigphoto_' + tid);
    if (bigPhoto) {
        var reader = new FileReader();
        reader.onload = function(e) {
            bigPhoto.innerHTML = '<img src="' + e.target.result + '">';
            bigPhoto.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }

    // Показываем статус загрузки
    var badge = document.getElementById('mcard_status_' + tid);
    if (badge) {
        badge.className = 'mfc-ai-badge';
        badge.style.background = '#EEF4FC';
        badge.style.border = '1px solid #bfdbfe';
        badge.style.color = '#1a4f8a';
        badge.innerHTML = '<span class="ai-spinner" style="width:14px;height:14px;border:2px solid #bfdbfe;border-top-color:#2C6DB5;border-radius:50%;flex-shrink:0;animation:spin .7s linear infinite;display:inline-block"></span> УМКА читает показание...';
        badge.style.display = 'flex';
    }

    // Отправляем на сервер — тип уже известен!
    recognizeMeterPhoto(file, tid, meterName);
}

// ── Отправка фото на сервер ──
async function recognizeMeterPhoto(file, tid, meterName) {
    var badge = document.getElementById('mcard_status_' + tid);
    var field = document.getElementById('field_meter_' + tid);
    var card  = document.getElementById('mcard_' + tid);

    try {
        var mL = meterName.toLowerCase();
        var base64Full = await fileToBase64(file);
        // Для каждого типа готовим специальный кроп:
        // Вода: верхняя половина (барабаны вверху, серийник внизу)
        // LCD (электро/тепло): вырезаем только дисплей и масштабируем до 1200px
        //   чтобы Vision чётко видел маленькие цифры на сегментном экране
        var base64Crop;
        if (mL.indexOf('электр') !== -1) {
            // NIK 2102: дисплей занимает правую часть, верхние ~55% высоты
            base64Crop = await cropAndScale(file, 0.42, 0.18, 0.58, 0.48, 1200);
        } else if (mL.indexOf('отопл') !== -1 || mL.indexOf('тепл') !== -1) {
            // ПУЛЬС: дисплей верхние ~38% высоты, по центру
            base64Crop = await cropAndScale(file, 0.08, 0.08, 0.84, 0.32, 1200);
        } else {
            // Вода: верхняя половина
            base64Crop = await cropImage(file, 0, 0, 1, 0.5);
        }

        var response = await fetch('umka_proxy.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                image_base64:      base64Full,
                image_base64_crop: base64Crop,
                media_type:        file.type || 'image/jpeg',
                meter_name:        meterName,
                meter_fields:      METER_FIELDS
            })
        });
        var text = await response.text();
        var data;
        try { data = JSON.parse(text); } catch(e) {
            throw new Error('Ответ сервера: ' + text.substring(0, 80));
        }
        if (data.error) throw new Error(data.error);

        console.log('УМКА [' + meterName + ']:', JSON.stringify(data));

        if (data.value !== null && data.value !== undefined) {
            // Заполняем поле
            field.value = parseFloat(data.value).toFixed(2);
            field.classList.add('ai-filled');
            setTimeout(function(){ field.classList.remove('ai-filled'); }, 900);

            // Подсвечиваем карточку
            if (card) card.classList.add('card-matched');

            // Бейдж успеха
            if (badge) {
                badge.className = 'mfc-ai-badge badge-ok';
                badge.innerHTML = '✓ Показание: <strong>' + parseFloat(data.value).toFixed(2) + '</strong>. Проверьте по фото:';
                badge.style.cssText = '';
            }

            if (window.showToast) showToast('✓ ' + meterName + ': ' + parseFloat(data.value).toFixed(2), 'success', 3000);
        } else {
            // Тип найден но показание не прочиталось
            // Подсказки под каждый тип счётчика
            var tips = {
                'электр': 'Сфотографируйте дисплей крупнее, без бликов',
                'отопл':  'Сфотографируйте дисплей крупнее, избегайте бликов на стекле',
                'тепл':   'Сфотографируйте дисплей крупнее, избегайте бликов на стекле',
                'горяч':  'Направьте камеру прямо на барабаны, без наклона',
            };
            var tip = 'Введите показание вручную';
            var mLow = meterName.toLowerCase();
            for (var key in tips) { if (mLow.indexOf(key) !== -1) { tip = tips[key]; break; } }

            if (badge) {
                badge.className = 'mfc-ai-badge';
                badge.style.background = '#fffbeb';
                badge.style.border = '1px solid #fde68a';
                badge.style.color = '#92400e';
                badge.innerHTML = '⚠ ' + tip;
            }
            if (field) { field.style.borderColor = '#f59e0b'; field.placeholder = '← по фото'; field.focus(); }
        }

    } catch(err) {
        console.error('УМКА ошибка [' + meterName + ']:', err.message);
        if (badge) {
            badge.className = 'mfc-ai-badge';
            badge.style.background = '#fef2f2';
            badge.style.border = '1px solid #fecaca';
            badge.style.color = '#b91c1c';
            badge.innerHTML = '✗ ' + err.message;
        }
    }
}

// ── Кроп изображения ──
function cropImage(file, x, y, w, h) {
    return new Promise(function(resolve) {
        var img = new Image();
        var url = URL.createObjectURL(file);
        img.onload = function() {
            URL.revokeObjectURL(url);
            var canvas = document.createElement('canvas');
            var srcW = Math.floor(img.width * w);
            var srcH = Math.floor(img.height * h);
            var scale = Math.min(2, 900 / Math.max(srcW, srcH));
            canvas.width  = Math.round(srcW * scale);
            canvas.height = Math.round(srcH * scale);
            canvas.getContext('2d').drawImage(img,
                Math.floor(img.width*x), Math.floor(img.height*y),
                srcW, srcH, 0, 0, canvas.width, canvas.height);
            resolve(canvas.toDataURL('image/jpeg', 0.92).split(',')[1]);
        };
        img.onerror = function(){ resolve(null); };
        img.src = url;
    });
}

// ── base64 ──
// ── Вырезка области дисплея с масштабированием ───────────────────────────────
// Вырезает прямоугольник (startX,startY,w,h в долях 0..1) и масштабирует
// до targetSize пикселей — Vision лучше видит маленькие цифры на крупном фото.
function cropAndScale(file, startX, startY, w, h, targetSize) {
    return new Promise(function(resolve) {
        var img = new Image();
        var url = URL.createObjectURL(file);
        img.onload = function() {
            URL.revokeObjectURL(url);
            var sx = Math.floor(img.width  * startX);
            var sy = Math.floor(img.height * startY);
            var sw = Math.floor(img.width  * w);
            var sh = Math.floor(img.height * h);
            if (sw < 1 || sh < 1) { resolve(null); return; }
            // Масштабируем так чтобы большая сторона = targetSize
            var scale = targetSize / Math.max(sw, sh);
            var canvas = document.createElement('canvas');
            canvas.width  = Math.round(sw * scale);
            canvas.height = Math.round(sh * scale);
            var ctx = canvas.getContext('2d');
            ctx.drawImage(img, sx, sy, sw, sh, 0, 0, canvas.width, canvas.height);
            resolve(canvas.toDataURL('image/jpeg', 0.95).split(',')[1]);
        };
        img.onerror = function() { resolve(null); };
        img.src = url;
    });
}


function fileToBase64(file) {
    return new Promise(function(resolve, reject) {
        var r = new FileReader();
        r.onload = function(e) { resolve(e.target.result.split(',')[1]); };
        r.onerror = reject;
        r.readAsDataURL(file);
    });
}

// ── Сброс ──
function resetUmka() {
    document.querySelector('form').reset();
    METER_FIELDS.forEach(function(f) {
        var badge = document.getElementById('mcard_status_' + f.id);
        var thumb = document.getElementById('mcard_thumb_'  + f.id);
        var big   = document.getElementById('mcard_bigphoto_' + f.id);
        var card  = document.getElementById('mcard_' + f.id);
        var field = document.getElementById('field_meter_' + f.id);
        var zone  = document.getElementById('mfc_zone_' + f.id);
        if (badge) { badge.style.display='none'; badge.innerHTML=''; badge.style.cssText='display:none'; }
        if (thumb) { thumb.style.display='none'; thumb.innerHTML=''; }
        if (big)   { big.style.display='none';   big.innerHTML=''; }
        if (card)  card.classList.remove('card-matched');
        if (field) { field.placeholder='Текущее показание'; field.style.cssText=''; }
    });
    document.getElementById('umkaStatusWrap').style.display = 'none';
}
</script>
</script>

<?php if ($success): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ showToast(<?= json_encode($success) ?>, 'success'); });</script>
<?php endif; ?>
<?php if ($error && $error !== 'Квартира не привязана к вашему аккаунту. Обратитесь к администратором.'): ?>
<script>document.addEventListener('DOMContentLoaded',function(){ showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>
</body>
</html>