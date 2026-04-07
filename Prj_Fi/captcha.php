<?php
/**
 * captcha.php - Генератор капчи с случайным абстрактным фоном
 */
session_start();
header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');

// ============================================
// НАСТРОЙКИ
// ============================================
$CODE_LENGTH = 6;
$IMG_WIDTH = 200;
$IMG_HEIGHT = 50;
$SESSION_KEY = 'captcha_code';
// ============================================

// Генерируем код
$captchaCode = '';
for ($i = 0; $i < $CODE_LENGTH; $i++) {
    $captchaCode .= rand(0, 9);
}
$_SESSION[$SESSION_KEY] = $captchaCode;

// Создаём изображение
$im = imagecreatetruecolor($IMG_WIDTH, $IMG_HEIGHT);

// Тёмный фон
$bgColor = imagecolorallocate($im, rand(10, 35), rand(10, 35), rand(20, 55));
imagefill($im, 0, 0, $bgColor);

// Фоновые круги
for ($c = 0; $c < 4; $c++) {
    $col = imagecolorallocatealpha($im, rand(30, 150), rand(30, 150), rand(50, 200), 35);
    imagefilledellipse($im, rand(0, $IMG_WIDTH), rand(0, $IMG_HEIGHT), rand(15, 30), rand(15, 30), $col);
}

// Пиксельные матрицы цифр 5x7
$digits = [
    [[0,1,1,1,0],[1,0,0,0,1],[1,0,0,1,1],[1,0,1,0,1],[1,1,0,0,1],[1,0,0,0,1],[0,1,1,1,0]],
    [[0,0,1,0,0],[0,1,1,0,0],[0,0,1,0,0],[0,0,1,0,0],[0,0,1,0,0],[0,0,1,0,0],[0,1,1,1,0]],
    [[0,1,1,1,0],[1,0,0,0,1],[0,0,0,0,1],[0,0,0,1,0],[0,0,1,0,0],[0,1,0,0,0],[1,1,1,1,1]],
    [[0,1,1,1,0],[1,0,0,0,1],[0,0,0,0,1],[0,0,1,1,0],[0,0,0,0,1],[1,0,0,0,1],[0,1,1,1,0]],
    [[0,0,0,1,0],[0,0,1,1,0],[0,1,0,1,0],[1,0,0,1,0],[1,1,1,1,1],[0,0,0,1,0],[0,0,0,1,0]],
    [[1,1,1,1,1],[1,0,0,0,0],[1,1,1,1,0],[0,0,0,0,1],[0,0,0,0,1],[1,0,0,0,1],[0,1,1,1,0]],
    [[0,0,1,1,0],[0,1,0,0,0],[1,0,0,0,0],[1,1,1,1,0],[1,0,0,0,1],[1,0,0,0,1],[0,1,1,1,0]],
    [[1,1,1,1,1],[0,0,0,0,1],[0,0,0,1,0],[0,0,1,0,0],[0,1,0,0,0],[0,1,0,0,0],[0,1,0,0,0]],
    [[0,1,1,1,0],[1,0,0,0,1],[1,0,0,0,1],[0,1,1,1,0],[1,0,0,0,1],[1,0,0,0,1],[0,1,1,1,0]],
    [[0,1,1,1,0],[1,0,0,0,1],[1,0,0,0,1],[0,1,1,1,1],[0,0,0,0,1],[0,0,0,1,0],[0,1,1,0,0]],
];

$pxScale = 2;

// Рисуем цифры
for ($i = 0; $i < $CODE_LENGTH; $i++) {
    $digit = (int)$captchaCode[$i];
    $matrix = $digits[$digit];
    
    $textR = rand(220, 255);
    $textG = rand(220, 255);
    $textB = rand(220, 255);
    
    // Случайное смещение
    $baseX = 8 + ($i * 31) + rand(-3, 3);
    $baseY = rand(12, 28);
    
    // Обводка
    $outlineColor = imagecolorallocate($im, 3, 3, 3);
    for ($row = 0; $row < 7; $row++) {
        for ($col = 0; $col < 5; $col++) {
            if ($matrix[$row][$col]) {
                $px = $baseX + ($col * $pxScale);
                $py = $baseY + ($row * $pxScale);
                imagefilledrectangle($im, $px - 1, $py - 1, $px + $pxScale, $py + $pxScale, $outlineColor);
            }
        }
    }
    
    // Заполнение с лёгкими «дырками» (~5%)
    $fillColor = imagecolorallocate($im, $textR, $textG, $textB);
    for ($row = 0; $row < 7; $row++) {
        for ($col = 0; $col < 5; $col++) {
            if ($matrix[$row][$col] && rand(0, 100) < 95) {
                $px = $baseX + ($col * $pxScale);
                $py = $baseY + ($row * $pxScale);
                imagefilledrectangle($im, $px, $py, $px + $pxScale - 1, $py + $pxScale - 1, $fillColor);
            }
        }
    }
}

// ---------- УМЕРЕННЫЙ ШУМ ----------

// 4-5 линий
for ($d = 0; $d < rand(4, 5); $d++) {
    $dc = imagecolorallocatealpha($im, rand(80, 255), rand(80, 255), rand(80, 255), rand(40, 60));
    imagesetthickness($im, rand(1, 2));
    imageline($im, rand(0, $IMG_WIDTH), rand(0, $IMG_HEIGHT), rand(0, $IMG_WIDTH), rand(0, $IMG_HEIGHT), $dc);
}

// 35 шумовых пикселей
for ($n = 0; $n < 35; $n++) {
    $nc = imagecolorallocatealpha($im, rand(60, 255), rand(60, 255), rand(60, 255), rand(35, 50));
    imagesetpixel($im, rand(0, $IMG_WIDTH - 1), rand(0, $IMG_HEIGHT - 1), $nc);
}

// 8 «капель»
for ($dot = 0; $dot < 8; $dot++) {
    $dotCol = imagecolorallocatealpha($im, rand(80, 255), rand(80, 255), rand(80, 255), rand(30, 45));
    imagefilledellipse($im, rand(0, $IMG_WIDTH), rand(0, $IMG_HEIGHT), rand(2, 4), rand(2, 4), $dotCol);
}

// 5 дуг
for ($a = 0; $a < 5; $a++) {
    $arcCol = imagecolorallocatealpha($im, rand(100, 255), rand(100, 255), rand(150, 255), 30);
    imagesetthickness($im, 1);
    imagearc($im, rand(10, $IMG_WIDTH - 10), rand(10, $IMG_HEIGHT - 10), rand(15, 30), rand(15, 30), rand(0, 360), rand(0, 360), $arcCol);
}

imagesetthickness($im, 1);
imagepng($im);
imagedestroy($im);
exit;
