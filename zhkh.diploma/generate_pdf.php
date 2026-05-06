<?php
session_start();
require_once 'includes/config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['invoice_id'])) {
    die('Неверный запрос');
}

$invoiceId = (int)$_POST['invoice_id'];
$user = getCurrentUser();
$db = db();

$stmt = $db->prepare("
    SELECT i.*, a.apartment_number, a.address, a.area 
    FROM invoices i 
    JOIN apartments a ON i.apartment_id = a.apartment_id 
    WHERE i.invoice_id = ?
");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch();

if (!$invoice) die('Квитанция не найдена');

if (hasRole('resident')) {
    $stmt = $db->prepare("SELECT * FROM apartments WHERE owner_user_id = ? AND apartment_id = ?");
    $stmt->execute([$user['user_id'], $invoice['apartment_id']]);
    if (!$stmt->fetch()) die('Доступ запрещён');
}

function getRussianMonth($dateStr) {
    $months = [
        'January'=>'Январь','February'=>'Февраль','March'=>'Март',
        'April'=>'Апрель','May'=>'Май','June'=>'Июнь',
        'July'=>'Июль','August'=>'Август','September'=>'Сентябрь',
        'October'=>'Октябрь','November'=>'Ноябрь','December'=>'Декабрь'
    ];
    $d = date('F Y', strtotime($dateStr.'-01'));
    foreach ($months as $en=>$ru) $d = str_replace($en,$ru,$d);
    return $d;
}

$details = $invoice['details_json'] ? json_decode($invoice['details_json'], true) : [];
$isPaid   = $invoice['payment_status'] === 'paid';
$debt     = $invoice['total_amount'] - $invoice['paid_amount'];

$detailsRows = '';
if (!empty($details)) {
    foreach ($details as $d) {
        $detailsRows .= '<tr>
            <td>'.htmlspecialchars($d['meter_type']).'</td>
            <td class="center">'.number_format($d['difference'],2,',',' ').'</td>
            <td class="center">'.number_format($d['price'],2,',',' ').' ₽</td>
            <td class="right"><b>'.number_format($d['amount'],2,',',' ').' ₽</b></td>
        </tr>';
    }
}

$html = '<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Квитанция ЖКХ — '.getRussianMonth($invoice['invoice_month']).'</title>
<style>
    @import url("https://fonts.googleapis.com/css2?family=PT+Sans:wght@400;700&display=swap");
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: "PT Sans", Arial, sans-serif;
        background: #f0f4f8;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding: 2rem 1rem 3rem;
        min-height: 100vh;
        color: #1A2540;
    }
    .page {
        background: #fff;
        width: 680px;
        max-width: 100%;
        border-radius: 16px;
        box-shadow: 0 8px 40px rgba(0,0,0,.12);
        overflow: hidden;
    }
    /* Шапка */
    .header {
        background: linear-gradient(135deg, #2C6DB5 0%, #1a4f8a 100%);
        color: #fff;
        padding: 1.75rem 2rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .header-left {}
    .header-logo {
        display: flex;
        align-items: center;
        gap: .6rem;
        margin-bottom: .4rem;
    }
    .header-logo-icon {
        width: 36px; height: 36px;
        background: rgba(255,255,255,.2);
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
    }
    .header-logo-text { font-size: 1.25rem; font-weight: 700; }
    .header-sub { font-size: .78rem; opacity: .75; }
    .header-right { text-align: right; }
    .header-right .period { font-size: 1rem; font-weight: 700; }
    .header-right .doc-num { font-size: .8rem; opacity: .7; margin-top: .2rem; }
    .header-divider { height: 3px; background: linear-gradient(90deg,#f8b500,#ff6b6b,#48dbfb); }

    /* Основной контент */
    .body { padding: 1.75rem 2rem; }

    /* Информационный блок */
    .info-block {
        background: #f8fafc;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: .65rem 1rem;
        border-bottom: 1px solid #e2e8f0;
        font-size: .88rem;
    }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: #5A6880; font-weight: 400; }
    .info-value { color: #1A2540; font-weight: 700; text-align: right; }
    .badge-paid   { background: #d1fae5; color: #065f46; padding: .25rem .75rem; border-radius: 20px; font-size: .8rem; }
    .badge-unpaid { background: #fee2e2; color: #991b1b; padding: .25rem .75rem; border-radius: 20px; font-size: .8rem; }

    /* Итого */
    .total-box {
        background: linear-gradient(135deg, #EEF4FC 0%, #dbeafe 100%);
        border: 2px solid #2C6DB5;
        border-radius: 12px;
        padding: 1.25rem 1.5rem;
        text-align: center;
        margin-bottom: 1.25rem;
    }
    .total-label { font-size: .82rem; color: #5A6880; text-transform: uppercase; letter-spacing: .06em; margin-bottom: .3rem; }
    .total-amount { font-size: 2.4rem; font-weight: 700; color: #2C6DB5; line-height: 1.1; }

    /* Сетка начислено/оплачено/остаток */
    .summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: .75rem;
        margin-bottom: 1.5rem;
    }
    .summary-item {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: .85rem;
        text-align: center;
    }
    .summary-item-label { font-size: .75rem; color: #9DAABF; margin-bottom: .25rem; }
    .summary-item-value { font-size: 1.05rem; font-weight: 700; color: #1A2540; }
    .summary-item.s-paid .summary-item-value   { color: #1DB954; }
    .summary-item.s-debt .summary-item-value   { color: #EF4444; }

    /* Детализация */
    .section-title {
        font-size: .78rem;
        font-weight: 700;
        color: #9DAABF;
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-bottom: .75rem;
    }
    table { width: 100%; border-collapse: collapse; font-size: .85rem; margin-bottom: 1.5rem; }
    thead { background: #2C6DB5; color: #fff; }
    th { padding: .6rem .9rem; text-align: left; font-size: .78rem; font-weight: 700; white-space: nowrap; }
    td { padding: .6rem .9rem; border-bottom: 1px solid #e2e8f0; }
    tr:last-child td { border-bottom: none; }
    tr:nth-child(even) td { background: #fafbfd; }
    .center { text-align: center; }
    .right  { text-align: right; }

    /* Футер квитанции */
    .receipt-footer {
        border-top: 1px solid #e2e8f0;
        padding-top: 1rem;
        text-align: center;
        font-size: .75rem;
        color: #9DAABF;
        line-height: 1.6;
    }
    .receipt-footer strong { color: #5A6880; }

    @media print {
        body { background: #fff; padding: 0; }
        .page { box-shadow: none; border-radius: 0; }
    }
    @media (max-width: 720px) {
        .header { flex-direction: column; gap: .75rem; }
        .header-right { text-align: left; }
        .summary-grid { grid-template-columns: 1fr; }
        .total-amount { font-size: 1.8rem; }
    }
</style>
</head>
<body>
<div class="page">
    <div class="header">
        <div class="header-left">
            <div class="header-logo">
                <div class="header-logo-icon">🏢</div>
                <span class="header-logo-text">ДомУчет</span>
            </div>
            <div class="header-sub">Система управления жилищно-коммунальным хозяйством</div>
        </div>
        <div class="header-right">
            <div class="period">КВИТАНЦИЯ ЖКХ</div>
            <div class="doc-num">№ КВ-'.str_pad($invoiceId,6,'0',STR_PAD_LEFT).' · '.date('d.m.Y').'</div>
        </div>
    </div>
    <div class="header-divider"></div>

    <div class="body">
        <div class="info-block">
            <div class="info-row">
                <span class="info-label">Период</span>
                <span class="info-value">'.getRussianMonth($invoice['invoice_month']).'</span>
            </div>
            <div class="info-row">
                <span class="info-label">Адрес</span>
                <span class="info-value">'.htmlspecialchars($invoice['address']).'</span>
            </div>
            <div class="info-row">
                <span class="info-label">Квартира</span>
                <span class="info-value">№ '.htmlspecialchars($invoice['apartment_number']).'</span>
            </div>
            <div class="info-row">
                <span class="info-label">Площадь</span>
                <span class="info-value">'.number_format($invoice['area'],2,',',' ').' м²</span>
            </div>
            <div class="info-row">
                <span class="info-label">Срок оплаты</span>
                <span class="info-value">'.date('d.m.Y', strtotime($invoice['due_date'])).'</span>
            </div>
            <div class="info-row">
                <span class="info-label">Статус</span>
                <span class="info-value">
                    <span class="'.($isPaid ? 'badge-paid' : 'badge-unpaid').'">
                        '.($isPaid ? '✓ Оплачено' : '○ Не оплачено').'
                    </span>
                </span>
            </div>
        </div>

        <div class="total-box">
            <div class="total-label">Итого к оплате</div>
            <div class="total-amount">'.number_format($debt,2,',',' ').' ₽</div>
        </div>

        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-item-label">Начислено</div>
                <div class="summary-item-value">'.number_format($invoice['total_amount'],2,',',' ').' ₽</div>
            </div>
            <div class="summary-item s-paid">
                <div class="summary-item-label">Оплачено</div>
                <div class="summary-item-value">'.number_format($invoice['paid_amount'],2,',',' ').' ₽</div>
            </div>
            <div class="summary-item s-debt">
                <div class="summary-item-label">Остаток</div>
                <div class="summary-item-value">'.number_format($debt,2,',',' ').' ₽</div>
            </div>
        </div>';

if (!empty($details)) {
    $html .= '<div class="section-title">Детализация начислений</div>
        <table>
            <thead><tr>
                <th>Услуга</th>
                <th class="center">Расход</th>
                <th class="center">Тариф</th>
                <th class="right">Сумма</th>
            </tr></thead>
            <tbody>'.$detailsRows.'</tbody>
        </table>';
}

$html .= '        <div class="receipt-footer">
            <p><strong>Квитанция сформирована:</strong> '.date('d.m.Y в H:i').'</p>
            <p>© '.date('Y').' ДомУчет — Система управления ЖКХ</p>
            <p style="margin-top:.35rem;font-size:.7rem;">Документ создан автоматически и не требует подписи</p>
        </div>
        <div style="text-align:center;margin-top:1.25rem;padding-bottom:.5rem;">
            <button onclick="window.print()" style="background:#2C6DB5;color:white;border:none;border-radius:8px;padding:.7rem 1.75rem;font-size:.9rem;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:.5rem;">
                🖨️ Распечатать квитанцию
            </button>
        </div>
    </div>
</div>
</body>
</html>';

$filename = 'kvitanciya_'.$invoice['invoice_month'].'_kv'.$invoice['apartment_number'].'.html';
header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-cache, must-revalidate');

echo $html;
