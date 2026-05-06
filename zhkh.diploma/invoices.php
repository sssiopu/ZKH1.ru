<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/layout.php';
initFlashFromCookie();
requireLogin();

$user = getCurrentUser();
if (!$user) { redirect('login.php'); }
$db   = db();

// Функция перевода месяцев на русский
function getRussianMonth($dateStr) {
    $months = [
        'January' => 'Январь', 'February' => 'Февраль', 'March' => 'Март',
        'April' => 'Апрель', 'May' => 'Май', 'June' => 'Июнь',
        'July' => 'Июль', 'August' => 'Август', 'September' => 'Сентябрь',
        'October' => 'Октябрь', 'November' => 'Ноябрь', 'December' => 'Декабрь'
    ];
    $date = date('F Y', strtotime($dateStr . '-01'));
    foreach ($months as $en => $ru) {
        $date = str_replace($en, $ru, $date);
    }
    return $date;
}

$apartment = null;
if (hasRole('resident')) {
    $stmt = $db->prepare("SELECT * FROM apartments WHERE owner_user_id = ?");
    $stmt->execute([$user['user_id']]);
    $apartment = $stmt->fetch();
}

$invoices = [];
if ($apartment || hasRole('admin')) {
    if (hasRole('admin')) {
        $stmt = $db->query("SELECT i.*, a.apartment_number FROM invoices i JOIN apartments a ON i.apartment_id=a.apartment_id ORDER BY i.invoice_month DESC LIMIT 50");
    } else {
        $stmt = $db->prepare("SELECT * FROM invoices WHERE apartment_id=? ORDER BY invoice_month DESC");
        $stmt->execute([$apartment['apartment_id']]);
    }
    $invoices = $stmt->fetchAll();
}

$total_amount = $total_paid = $total_debt = $paid_count = 0;
foreach ($invoices as $inv) {
    $total_amount += $inv['total_amount'];
    $total_paid   += $inv['paid_amount'];
    if ($inv['payment_status'] === 'paid') $paid_count++;
}
$total_debt = $total_amount - $total_paid;

// Chart data - с русскими месяцами
$chartLabels = $chartPaid = $chartUnpaid = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $chartLabels[] = getRussianMonth($month);
    $found = false;
    foreach ($invoices as $inv) {
        if ($inv['invoice_month'] === $month) {
            $chartPaid[]   = floatval($inv['paid_amount']);
            $chartUnpaid[] = floatval($inv['total_amount'] - $inv['paid_amount']);
            $found = true; break;
        }
    }
    if (!$found) { $chartPaid[] = 0; $chartUnpaid[] = 0; }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Квитанции — ДомУчет</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?= pageStyles() ?>
</head>
<body>
<?= renderHeader($user, 'invoices') ?>

<section class="page-hero">
    <div class="page-hero-inner">
        <h1><svg viewBox="0 0 28 28" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:28px;height:28px;flex-shrink:0"><rect x="5" y="3" width="14" height="18" rx="2" fill="none"/><path d="M9 8h6M9 12h4M12 16v-3M10 16h4"/></svg> Квитанции</h1>
        <p>История начислений и оплата услуг ЖКХ</p>
    </div>
</section>

<main class="main">

    <?php if ($apartment || hasRole('admin')): ?>

    <?php if (hasRole('admin')): ?>
    <div style="background:#EEF4FF;border:1px solid #93C5FD;border-radius:12px;padding:.85rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem;font-size:.88rem;color:#1A2540;">
        <i class="fas fa-shield-alt" style="color:#2C6DB5;"></i>
        <span>Вы просматриваете <strong>все квитанции всех жильцов</strong> — это вид администратора.</span>
        <a href="admin/index.php?tab=invoices" style="margin-left:auto;color:#2C6DB5;font-weight:600;text-decoration:none;white-space:nowrap;">Открыть в панели →</a>
    </div>
    <?php endif; ?>

    <!-- Статистика -->
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon" style="width:36px;height:36px"><svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="6" y="4" width="16" height="20" rx="2" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2"/><path d="M10 10h8M10 14h6M10 18h4" stroke="#93C5FD" stroke-width="2" stroke-linecap="round"/><rect x="6" y="20" width="16" height="4" rx="0 0 2 2" fill="#2C6DB5"/></svg></div>
            <div><div class="stat-value"><?= count($invoices) ?></div><div class="stat-label">Всего квитанций</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="width:36px;height:36px"><svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="14" cy="14" r="10" fill="#f0fdf4" stroke="#1DB954" stroke-width="2"/><path d="M9 14l4 4 7-8" stroke="#1DB954" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
            <div><div class="stat-value" style="font-size:1.1rem;"><?= formatMoney($total_paid) ?></div><div class="stat-label">Оплачено</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="width:36px;height:36px"><svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="14" cy="14" r="10" fill="#fef2f2" stroke="#EF4444" stroke-width="2"/><path d="M14 9v6M14 18v1" stroke="#EF4444" stroke-width="2.5" stroke-linecap="round"/></svg></div>
            <div><div class="stat-value" style="font-size:1.1rem;"><?= formatMoney($total_debt) ?></div><div class="stat-label">К оплате</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="width:36px;height:36px"><svg viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="4" y="6" width="20" height="18" rx="2" fill="#EEF4FF" stroke="#2C6DB5" stroke-width="2"/><path d="M4 12h20" stroke="#93C5FD" stroke-width="1.5"/><path d="M9 4v4M19 4v4" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round"/><rect x="9" y="16" width="4" height="4" rx="1" fill="#EF4444"/></svg></div>
            <div><div class="stat-value"><?= $paid_count ?></div><div class="stat-label">Оплачено квитанций</div></div>
        </div>
    </div>

    <!-- График -->
    <div class="card">
        <div class="card-title"><svg viewBox="0 0 22 22" fill="none" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><rect x="2" y="14" width="4" height="6"/><rect x="9" y="8" width="4" height="12"/><rect x="16" y="4" width="4" height="16"/></svg> График платежей за 6 месяцев</div>
        <div style="height:280px;padding:.5rem 0;">
            <canvas id="paymentsChart"></canvas>
        </div>
    </div>

    <!-- Список квитанций -->
    <div class="card">
        <div class="card-title"><svg viewBox="0 0 22 22" fill="none" stroke="#2C6DB5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px"><path d="M8 6h10M8 11h10M8 16h10M4 6h.01M4 11h.01M4 16h.01"/></svg> История квитанций</div>

        <?php if (empty($invoices)): ?>
            <div class="alert alert-info"><i class="fas fa-info-circle"></i> Квитанции пока не сформированы. Передайте показания счётчиков.</div>
        <?php else: ?>
            <?php
            $statusText  = ['paid'=>'Оплачено','unpaid'=>'Не оплачено','partial'=>'Частично'];
            $statusBadge = ['paid'=>'badge-paid','unpaid'=>'badge-unpaid','partial'=>'badge-partial'];
            $statusIcon  = ['paid'=>'fa-check-circle','unpaid'=>'fa-times-circle','partial'=>'fa-exclamation-circle'];
            ?>
            <?php foreach ($invoices as $inv): ?>
            <?php $s = $inv['payment_status']; ?>
            <div class="invoice-card <?= $s ?>">
                <div class="invoice-head">
                    <div>
                        <div class="invoice-month">
                            <i class="fas fa-calendar-alt" style="color:var(--blue);"></i>
                            <?= getRussianMonth($inv['invoice_month']) ?>
                        </div>
                        <?php if (hasRole('admin')): ?>
                            <div style="font-size:.85rem;color:var(--gray-600);margin-top:.3rem;"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" style="width:14px;height:14px"><path d="M2 8L8 3l6 5"/><path d="M4 7v6h3v-3h2v3h3V7"/></svg> Квартира №<?= $inv['apartment_number'] ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="text-align:right;">
                        <div class="invoice-amount <?= $s ?>"><?= formatMoney($inv['total_amount']) ?></div>
                        <div style="margin-top:.4rem;">
                            <span class="badge <?= $statusBadge[$s] ?>"><i class="fas <?= $statusIcon[$s] ?>"></i> <?= $statusText[$s] ?></span>
                        </div>
                    </div>
                </div>
                <div class="invoice-details">
                    <div class="inv-detail"><div class="inv-detail-label">Начислено</div><div class="inv-detail-value"><?= formatMoney($inv['total_amount']) ?></div></div>
                    <div class="inv-detail"><div class="inv-detail-label">Оплачено</div><div class="inv-detail-value" style="color:var(--green)"><?= formatMoney($inv['paid_amount']) ?></div></div>
                    <div class="inv-detail"><div class="inv-detail-label">Остаток</div><div class="inv-detail-value" style="color:var(--red)"><?= formatMoney($inv['total_amount'] - $inv['paid_amount']) ?></div></div>
                    <div class="inv-detail"><div class="inv-detail-label">Срок оплаты</div><div class="inv-detail-value"><?= formatDate($inv['due_date']) ?></div></div>
                </div>
                <div class="btn-row" style="margin-top:0;">
                    <button class="btn-secondary" onclick="downloadPDF(<?= $inv['invoice_id'] ?>)" style="font-size:.85rem;padding:.5rem 1rem;"><i class="fas fa-file-pdf" style="margin-right:.35rem;"></i> Скачать PDF</button>
                    <?php if ($s !== 'paid'): ?>
                        <button class="btn-primary" onclick="showPaymentOptions(<?= $inv['invoice_id'] ?>, <?= $inv['total_amount'] - $inv['paid_amount'] ?>)" style="font-size:.85rem;padding:.5rem 1rem;"><i class="fas fa-credit-card" style="margin-right:.35rem;"></i> Оплатить</button>
                    <?php endif; ?>
                    <?php if (hasRole('admin')): ?>
                        <button class="btn-secondary" style="font-size:.85rem;padding:.5rem 1rem;" onclick="toggleBreakdown(this)"><i class="fas fa-eye" style="margin-right:.35rem;"></i> Детали</button>
                    <?php endif; ?>
                </div>
                <?php if (hasRole('admin')): ?>
                <div class="invoice-breakdown">
                    <?php if ($inv['details_json'] && $details = json_decode($inv['details_json'], true)): ?>
                        <div class="table-wrap" style="margin-top:.5rem;">
                            <table>
                                <thead><tr><th>Услуга</th><th>Расход</th><th>Тариф</th><th>Сумма</th></tr></thead>
                                <tbody>
                                    <?php foreach ($details as $d): ?>
                                    <tr>
                                        <td><?= escape($d['meter_type']) ?></td>
                                        <td><?= number_format($d['difference'], 2) ?></td>
                                        <td><?= formatMoney($d['price']) ?></td>
                                        <td><strong><?= formatMoney($d['amount']) ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color:var(--gray-400);font-size:.9rem;">Детализация недоступна</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php else: ?>
        <?php if (hasRole('admin')): ?>
        <div class="alert" style="background:#EEF4FF;border:1px solid #93C5FD;color:#1A2540;border-radius:12px;padding:1.25rem 1.5rem;display:flex;align-items:center;gap:1rem;">
            <i class="fas fa-shield-alt" style="color:#2C6DB5;font-size:1.3rem;"></i>
            <div>
                <strong>Режим администратора</strong><br>
                <span style="font-size:.9rem;">Все квитанции и платежи жильцов доступны в панели администратора.</span>
            </div>
            <a href="admin/index.php?tab=invoices" style="margin-left:auto;background:#2C6DB5;color:#fff;padding:.55rem 1.1rem;border-radius:8px;text-decoration:none;font-size:.88rem;font-weight:600;white-space:nowrap;">
                <i class="fas fa-arrow-right"></i> Перейти в панель
            </a>
        </div>
        <?php else: ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> Квартира не привязана к аккаунту. Обратитесь к администратору.</div>
        <?php endif; ?>
    <?php endif; ?>

</main>

<?= renderFooter() ?>

<!-- Модальное окно выбора способа оплаты -->
<div id="paymentModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:16px;padding:2rem;max-width:480px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.2);">
        <div style="text-align:center;">
            <div style="width:64px;height:64px;background:linear-gradient(135deg,#2C6DB5,#1a4f8a);border-radius:16px;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-credit-card" style="font-size:2rem;color:white;"></i>
            </div>
            <h3 style="font-size:1.4rem;margin-bottom:.5rem;color:var(--gray-900);">Выберите способ оплаты</h3>
            <p style="color:var(--gray-600);font-size:.95rem;margin-bottom:1.5rem;">Оплатите квитанцию удобным способом</p>
            
            <div style="background:var(--blue-light);border-radius:10px;padding:1rem;margin-bottom:1.5rem;">
                <div style="font-size:.85rem;color:var(--gray-600);margin-bottom:.3rem;">Сумма к оплате</div>
                <div id="paymentAmount" style="font-size:2rem;font-weight:700;color:var(--blue);"></div>
            </div>
            
            <!-- Кнопки выбора способа оплаты -->
            <div style="display:grid;gap:.75rem;margin-bottom:1rem;">
                <button onclick="payByCard()" class="btn-primary" style="width:100%;padding:1rem;font-size:1rem;display:flex;align-items:center;justify-content:center;gap:.5rem;">
                    <i class="fas fa-credit-card" style="font-size:1.2rem;"></i>
                    <span>Оплатить картой</span>
                </button>
                <button onclick="paySBP()" class="btn-primary" style="width:100%;padding:1rem;font-size:1rem;display:flex;align-items:center;justify-content:center;gap:.5rem;background:#4CAF50;">
                    <i class="fas fa-qrcode" style="font-size:1.2rem;"></i>
                    <span>Оплатить по СБП</span>
                </button>
            </div>
            
            <button onclick="closePaymentModal()" class="btn-secondary" style="width:100%;padding:.75rem;">Отмена</button>
        </div>
    </div>
</div>

<!-- Модальное окно оплаты картой -->
<div id="cardModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:10000;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:16px;padding:2rem;max-width:420px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.2);">
        <div style="text-align:center;">
            <div style="width:64px;height:64px;background:linear-gradient(135deg,#2C6DB5,#1a4f8a);border-radius:16px;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-credit-card" style="font-size:2rem;color:white;"></i>
            </div>
            <h3 style="font-size:1.3rem;margin-bottom:.5rem;color:var(--gray-900);">Оплата банковской картой</h3>
            <p style="color:var(--gray-600);font-size:.9rem;margin-bottom:1.5rem;">Введите данные вашей карты</p>
            
            <!-- Форма оплаты -->
            <div style="text-align:left;margin-bottom:1.5rem;">
                <div style="margin-bottom:1rem;">
                    <label style="display:block;font-size:.85rem;color:var(--gray-600);margin-bottom:.4rem;font-weight:600;">Номер карты</label>
                    <input type="text" id="cardNumber" placeholder="0000 0000 0000 0000" maxlength="19" style="width:100%;padding:.75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.95rem;">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1rem;">
                    <div>
                        <label style="display:block;font-size:.85rem;color:var(--gray-600);margin-bottom:.4rem;font-weight:600;">Срок действия</label>
                        <input type="text" id="cardExpiry" placeholder="MM/YY" maxlength="5" style="width:100%;padding:.75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.95rem;">
                    </div>
                    <div>
                        <label style="display:block;font-size:.85rem;color:var(--gray-600);margin-bottom:.4rem;font-weight:600;">CVV</label>
                        <input type="text" id="cardCVV" placeholder="000" maxlength="3" style="width:100%;padding:.75rem;border:1px solid var(--gray-200);border-radius:8px;font-size:.95rem;">
                    </div>
                </div>
            </div>
            
            <div style="background:var(--blue-light);border-radius:10px;padding:1rem;margin-bottom:1.5rem;">
                <div style="font-size:.85rem;color:var(--gray-600);margin-bottom:.3rem;">Сумма к оплате</div>
                <div id="cardAmount" style="font-size:1.8rem;font-weight:700;color:var(--blue);"></div>
            </div>
            
            <button onclick="processCardPayment()" class="btn-primary" style="width:100%;padding:.75rem;margin-bottom:.5rem;">Оплатить</button>
            <button onclick="closeCardModal()" class="btn-secondary" style="width:100%;padding:.75rem;">Отмена</button>
        </div>
    </div>
</div>

<!-- Модальное окно оплаты по СБП -->
<div id="sbpModal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:10000;align-items:center;justify-content:center;">
    <div style="background:white;border-radius:16px;padding:2rem;max-width:420px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.2);">
        <div style="text-align:center;">
            <div style="width:64px;height:64px;background:linear-gradient(135deg,#4CAF50,#45a049);border-radius:16px;margin:0 auto 1rem;display:flex;align-items:center;justify-content:center;">
                <i class="fas fa-qrcode" style="font-size:2rem;color:white;"></i>
            </div>
            <h3 style="font-size:1.3rem;margin-bottom:.5rem;color:var(--gray-900);">Оплата по СБП</h3>
            <p style="color:var(--gray-600);font-size:.9rem;margin-bottom:1.5rem;">Отсканируйте QR-код в приложении банка</p>
            
            <!-- QR-код -->
            <div style="background:#f5f5f5;border-radius:12px;padding:2rem;margin-bottom:1.5rem;">
                <div id="qrcode" style="display:flex;justify-content:center;"></div>
            </div>
            
            <div style="background:var(--blue-light);border-radius:10px;padding:1rem;margin-bottom:1.5rem;">
                <div style="font-size:.85rem;color:var(--gray-600);margin-bottom:.3rem;">Сумма к оплате</div>
                <div id="sbpAmount" style="font-size:1.8rem;font-weight:700;color:var(--blue);"></div>
            </div>
            
            <button onclick="closeSBP()" class="btn-primary" style="width:100%;padding:.75rem;">Закрыть</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let currentInvoiceId = null;
let currentAmount = 0;

function toggleBreakdown(btn) {
    const bd = btn.closest('.invoice-card').querySelector('.invoice-breakdown');
    const open = bd.style.display === 'block';
    bd.style.display = open ? 'none' : 'block';
    const icon = open ? 'fa-eye' : 'fa-eye-slash';
    const text = open ? 'Детали' : 'Скрыть';
    btn.innerHTML = `<i class="fas ${icon}" style="margin-right:.35rem;"></i> ${text}`;
}

function downloadPDF(invoiceId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'generate_pdf.php';
    form.target = '_blank';
    
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'invoice_id';
    input.value = invoiceId;
    
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
    showToast('Квитанция формируется и откроется в новой вкладке', 'info');
}

// Показать окно выбора способа оплаты
function showPaymentOptions(invoiceId, amount) {
    currentInvoiceId = invoiceId;
    currentAmount = amount;
    
    const modal = document.getElementById('paymentModal');
    const amountEl = document.getElementById('paymentAmount');
    
    amountEl.textContent = amount.toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₽';
    modal.style.display = 'flex';
}

function closePaymentModal() {
    document.getElementById('paymentModal').style.display = 'none';
}

// Оплата картой
function payByCard() {
    closePaymentModal();
    
    const modal = document.getElementById('cardModal');
    const amountEl = document.getElementById('cardAmount');
    
    amountEl.textContent = currentAmount.toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₽';
    modal.style.display = 'flex';
}

function closeCardModal() {
    document.getElementById('cardModal').style.display = 'none';
    // Очищаем поля
    document.getElementById('cardNumber').value = '';
    document.getElementById('cardExpiry').value = '';
    document.getElementById('cardCVV').value = '';
}

function processCardPayment() {
    const cardNumber = document.getElementById('cardNumber').value;
    const cardExpiry = document.getElementById('cardExpiry').value;
    const cardCVV = document.getElementById('cardCVV').value;
    
    if (!cardNumber || !cardExpiry || !cardCVV) {
        showToast('Пожалуйста, заполните все поля карты', 'warning');
        return;
    }
    
    // Здесь должна быть реальная интеграция с платежной системой
    showToast('Оплата картой принята! Обработка платежа...', 'success', 5000);
    closeCardModal();
}

// Форматирование номера карты
document.getElementById('cardNumber')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\s/g, '');
    let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
    e.target.value = formattedValue;
});

// Форматирование срока действия
document.getElementById('cardExpiry')?.addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length >= 2) {
        value = value.slice(0, 2) + '/' + value.slice(2, 4);
    }
    e.target.value = value;
});

// Оплата по СБП
let qrcodeInstance = null;

function paySBP() {
    closePaymentModal();
    
    const modal = document.getElementById('sbpModal');
    const qrContainer = document.getElementById('qrcode');
    const amountEl = document.getElementById('sbpAmount');
    
    amountEl.textContent = currentAmount.toLocaleString('ru-RU', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₽';
    
    // Очищаем предыдущий QR-код
    qrContainer.innerHTML = '';
    
    // Генерируем данные для СБП
    const sbpData = `https://qr.nspk.ru/AD10009ABM6KR329V7LQE2RGD0TRUF58?type=01&bank=100000000111&sum=${currentAmount}&cur=RUB&crc=AB75`;
    
    // Создаем QR-код
    qrcodeInstance = new QRCode(qrContainer, {
        text: sbpData,
        width: 200,
        height: 200,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });
    
    modal.style.display = 'flex';
}

function closeSBP() {
    document.getElementById('sbpModal').style.display = 'none';
}

// Закрытие по клику вне модального окна
document.getElementById('paymentModal')?.addEventListener('click', function(e) {
    if (e.target === this) closePaymentModal();
});
document.getElementById('cardModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeCardModal();
});
document.getElementById('sbpModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeSBP();
});

// График
const ctx = document.getElementById('paymentsChart');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                { label: 'Оплачено', data: <?= json_encode($chartPaid) ?>, backgroundColor: 'rgba(29,185,84,.75)', borderRadius: 6 },
                { label: 'Долг', data: <?= json_encode($chartUnpaid) ?>, backgroundColor: 'rgba(239,68,68,.65)', borderRadius: 6 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' }, tooltip: { callbacks: { label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString('ru-RU') + ' ₽' } } },
            scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString('ru-RU') + ' ₽' }, grid: { color: 'rgba(0,0,0,.04)' } }, x: { grid: { display: false } } }
        }
    });
}
</script>
</body>
</html>
