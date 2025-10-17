<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

$uploadDir = __DIR__ . '/uploads/';

// Создаём папку uploads, если её нет
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// GET - список всех файлов
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $files = [];
    $items = scandir($uploadDir);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $filePath = $uploadDir . $item;
        if (is_file($filePath)) {
            $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
            $imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

            if (in_array($ext, $imageExts)) {
                $files[] = [
                    'name' => $item,
                    'url' => 'uploads/' . $item,
                    'size' => filesize($filePath),
                    'modified' => filemtime($filePath)
                ];
            }
        }
    }

    // Сортируем по дате (новые сначала)
    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });

    echo json_encode(['success' => true, 'files' => $files]);
    exit;
}

// POST - загрузка файла
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'Файл не загружен']);
        exit;
    }

    $file = $_FILES['file'];

    // Проверка на ошибки
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'error' => 'Ошибка загрузки файла']);
        exit;
    }

    // Проверка типа файла
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        echo json_encode(['success' => false, 'error' => 'Недопустимый тип файла. Разрешены только изображения.']);
        exit;
    }

    // Проверка размера (макс 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'Файл слишком большой. Максимум 5MB.']);
        exit;
    }

    // Генерация уникального имени
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $baseName = pathinfo($file['name'], PATHINFO_FILENAME);
    $baseName = preg_replace('/[^a-zA-Z0-9-_]/', '-', $baseName);
    $fileName = $baseName . '-' . time() . '.' . $ext;

    $targetPath = $uploadDir . $fileName;

    // Перемещаем файл
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Для обычной галереи
        $response = [
            'success' => true,
            'file' => [
                'name' => $fileName,
                'url' => 'uploads/' . $fileName,
                'size' => filesize($targetPath)
            ]
        ];

        // Для CKEditor SimpleUploadAdapter добавляем поле 'url'
        $response['url'] = 'uploads/' . $fileName;

        echo json_encode($response);
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось сохранить файл']);
    }
    exit;
}

// DELETE - удаление файла
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['filename'])) {
        echo json_encode(['success' => false, 'error' => 'Имя файла не указано']);
        exit;
    }

    $fileName = basename($data['filename']); // Защита от path traversal
    $filePath = $uploadDir . $fileName;

    if (!file_exists($filePath)) {
        echo json_encode(['success' => false, 'error' => 'Файл не найден']);
        exit;
    }

    if (unlink($filePath)) {
        echo json_encode(['success' => true, 'message' => 'Файл удалён']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Не удалось удалить файл']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Недопустимый метод']);
