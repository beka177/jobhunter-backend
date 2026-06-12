<?php
// Загрузка файлов (аватары, картинки вакансий).
// Принимает multipart/form-data; валидирует MIME и размер; кладёт файл в /uploads/.
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$kind = $_POST['kind'] ?? '';
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;

if (!in_array($kind, ['avatar', 'vacancy'], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid kind']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['file']['error'] ?? 'no file';
    http_response_code(400);
    // UPLOAD_ERR_INI_SIZE / UPLOAD_ERR_FORM_SIZE — слишком большой для php.ini
    if (in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        echo json_encode(['error' => 'file too large']);
    } else {
        echo json_encode(['error' => 'upload failed', 'code' => $code]);
    }
    exit;
}

$file = $_FILES['file'];
// Ограничение по размеру убрано — принимаем файл любого размера
// (верхний предел задаётся только upload_max_filesize в php.ini).

// MIME через finfo (не доверяем заголовку клиента и расширению)
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
if (!isset($allowed[$mime])) {
    http_response_code(415);
    echo json_encode(['error' => 'unsupported format', 'mime' => $mime]);
    exit;
}
$ext = $allowed[$mime];

// Папка назначения — в корне домена, не внутри api/
$subdir = $kind === 'avatar' ? 'avatars' : 'vacancies';
$diskDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $subdir;
if (!is_dir($diskDir)) {
    if (!mkdir($diskDir, 0755, true) && !is_dir($diskDir)) {
        http_response_code(500);
        echo json_encode(['error' => 'cannot create uploads dir']);
        exit;
    }
}

// Имя: timestamp_random.ext
$name = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$fullPath = $diskDir . DIRECTORY_SEPARATOR . $name;

// Ресайз через GD (если доступно)
$resized = false;
if (function_exists('imagecreatefromstring')) {
    $imgData = file_get_contents($file['tmp_name']);
    $src = @imagecreatefromstring($imgData);
    if ($src !== false) {
        $srcW = imagesx($src);
        $srcH = imagesy($src);
        if ($kind === 'avatar') {
            // Квадрат 512×512 с обрезкой по центру
            $size = 512;
            $minSide = min($srcW, $srcH);
            $offX = (int)(($srcW - $minSide) / 2);
            $offY = (int)(($srcH - $minSide) / 2);
            $dst = imagecreatetruecolor($size, $size);
            // Прозрачность для PNG/WEBP
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, $offX, $offY, $size, $size, $minSide, $minSide);
        } else {
            // Вакансия: ширина не более 1600 px, пропорционально
            $maxW = 1600;
            if ($srcW > $maxW) {
                $newW = $maxW;
                $newH = (int)round($srcH * ($maxW / $srcW));
            } else {
                $newW = $srcW;
                $newH = $srcH;
            }
            $dst = imagecreatetruecolor($newW, $newH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
        }

        $ok = false;
        if ($ext === 'jpg') {
            $ok = imagejpeg($dst, $fullPath, 85);
        } elseif ($ext === 'png') {
            $ok = imagepng($dst, $fullPath, 6);
        } elseif ($ext === 'webp') {
            $ok = imagewebp($dst, $fullPath, 85);
        }
        imagedestroy($src);
        imagedestroy($dst);
        $resized = $ok;
    }
}

if (!$resized) {
    // Фолбэк: просто перенесли как есть
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        http_response_code(500);
        echo json_encode(['error' => 'cannot save file']);
        exit;
    }
}

// Удаление предыдущего файла при замене аватара
if ($kind === 'avatar' && $userId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $prev = $stmt->fetchColumn();
        if ($prev && strpos($prev, '/uploads/avatars/') !== false) {
            // Превращаем URL в локальный путь и удаляем
            $prevName = basename(parse_url($prev, PHP_URL_PATH) ?: $prev);
            $prevPath = $diskDir . DIRECTORY_SEPARATOR . $prevName;
            if (is_file($prevPath) && $prevPath !== $fullPath) {
                @unlink($prevPath);
            }
        }
    } catch (PDOException $e) { /* не критично */ }
}

// Формируем публичный URL
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'jobsearch';
$url = $scheme . '://' . $host . '/uploads/' . $subdir . '/' . $name;

echo json_encode(['url' => $url], JSON_UNESCAPED_UNICODE);
