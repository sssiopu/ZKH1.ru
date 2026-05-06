<?php
/**
 * УМКА — Универсальный прокси-парсер (Финальная версия)
 * Исправлено: игнорирование модели "15" и корректный парсинг барабанов и ЖК-дисплеев.
 */

define('YANDEX_API_KEY',   'AQVNy-iuD9Ckr5PZB9D6ryorhoQ6c-37iz_HCPUw');
define('YANDEX_FOLDER_ID', 'b1gk5ugpig15ibqi0jjc');

session_start();
require_once 'includes/config.php';
header('Content-Type: application/json; charset=utf-8');

// Проверка авторизации
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$imageBase64 = $input['image_base64'] ?? '';

if (empty($imageBase64)) {
    echo json_encode(['error' => 'No image provided']);
    exit;
}

// 1. Получаем текст через Яндекс Vision
$ocrText = getOCRText($imageBase64);

// 2. Интеллектуальный парсинг
$value = parseSmart($ocrText);

// 3. Ответ фронтенду
echo json_encode([
    'value'      => $value,
    '_raw_ocr'   => $ocrText, // Для отладки
    'status'     => ($value !== null) ? 'success' : 'not_found'
]);

/**
 * Функция взаимодействия с Vision API
 */
function getOCRText($base64) {
    $body = json_encode([
        'folderId' => YANDEX_FOLDER_ID,
        'analyze_specs' => [[
            'content' => $base64,
            'features' => [[
                'type' => 'TEXT_DETECTION', 
                'text_detection_config' => ['language_codes' => ['en', 'ru']]
            ]]
        ]]
    ]);

    $ch = curl_init('https://vision.api.cloud.yandex.net/vision/v1/batchAnalyze');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Api-Key ' . YANDEX_API_KEY
        ],
    ]);

    $response = curl_exec($ch);
    $data = json_decode($response, true);
    curl_close($ch);

    $parts = [];
    $blocks = $data['results'][0]['results'][0]['textDetection']['pages'][0]['blocks'] ?? [];
    foreach ($blocks as $block) {
        foreach ($block['lines'] as $line) {
            foreach ($line['words'] as $word) {
                $parts[] = $word['text'];
            }
        }
    }
    return implode(' ', $parts);
}

/**
 * Умный парсер с защитой от "шума" модели
 */
function parseSmart($text) {
    if (empty($text)) return null;

    // 1. Нормализация текста
    $text = str_ireplace(['O', 'I', 'S', 'B'], ['0', '1', '5', '8'], $text);
    
    // Стираем "15" и "1.6" только если они стоят отдельно, чтобы не убить реальные показания
    $text = preg_replace('/\b15[УУYy]?\b/iu', ' ', $text); 
    $text = preg_replace('/\b1[.,]6\b/', ' ', $text);      

    // 2. Сначала ищем "идеальные" случаи (Отопление и Барабаны с нулями)
    
    // А) Отопление: ищем число с 3 знаками после точки (15.814)
    if (preg_match('/\b\d+[.,]\d{3}\b/', $text, $matches)) {
        return (float)str_replace(',', '.', $matches[0]);
    }

    // Б) Вода: ищем длинные цепочки, начинающиеся с нулей (000237 или 00000)
    if (preg_match_all('/\b0+\d*\b/', $text, $matches)) {
        foreach ($matches[0] as $match) {
            // Если это просто нули (00000) или число типа 00237
            if (strlen($match) >= 3) {
                return (float)substr($match, 0, 5); 
            }
        }
    }

    // 3. Если "идеальных" нет, ищем любого кандидата (Свет или Вода без нулей)
    if (preg_match_all('/\d+([.,]\d+)?/', $text, $matches)) {
        $bestVal = null;
        $maxScore = -1;

        foreach ($matches[0] as $raw) {
            $valStr = str_replace(',', '.', $raw);
            $val = (float)$valStr;
            $score = 0;

            // Если число 3-5 знаков (как 5885 на свету или 237 на воде)
            $len = strlen(preg_replace('/\D/', '', $raw));
            if ($len >= 3 && $len <= 5) $score += 100;
            
            // Если есть точка (ЖК экран)
            if (strpos($valStr, '.') !== false) $score += 50;

            if ($score > $maxScore) {
                $maxScore = $score;
                $bestVal = $val;
            }
        }
        return $bestVal;
    }

    return null;
}