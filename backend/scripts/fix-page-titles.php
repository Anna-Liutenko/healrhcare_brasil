<?php
declare(strict_types=1);

$updates = [
    'new-page-1762478956506' => [
        'title' => 'Медицина в Бразилии: Ваш гид по сложной системе',
        'seo_title' => 'Медицина в Бразилии: Ваш гид по сложной системе',
        'seo_description' => 'Помогаю русскоязычным экспатам разобраться в системе здравоохранения Бразилии: SUS, частные страховки, проверенные врачи.',
    ],
    'all-materials' => [
        'title' => 'Все материалы',
        'seo_title' => 'Все материалы - Healthcare Hacks Brazil',
        'seo_description' => 'Все материалы Healthcare Hacks Brazil: гайды и статьи о медицине в Бразилии для экспатов.',
    ],
    'sobiraem-kollektsiyu-1' => [
        'title' => 'Полный гайд по SUS для экспата',
        'seo_title' => 'Полный гайд по SUS для экспата',
        'seo_description' => 'Пошаговое руководство по системе SUS в Бразилии для экспатов.',
    ],
    'sobiraem-kollektsiyu-2' => [
        'title' => 'Полезные гайды',
        'seo_title' => 'Полезные гайды',
        'seo_description' => 'Подборка полезных гайдов по медицинской системе Бразилии.',
    ],
    'new-page-1762829982276' => [
        'title' => 'Привет, я Анна Лютенко!',
        'seo_title' => 'Привет, я Анна Лютенко!',
        'seo_description' => 'Анна Лютенко делится опытом и помогает решить медицинские вопросы в Бразилии.',
    ],
    'testiruem-vosstanovlenie' => [
        'title' => 'Тестируем восстановление',
        'seo_title' => 'Тестируем восстановление 1762826690038',
        'seo_description' => 'Тестовая страница для проверки восстановления контента.',
    ],
];

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;dbname=healthcare_cms;charset=utf8mb4',
        'root',
        ''
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    fwrite(STDERR, "Failed to connect to database: " . $e->getMessage() . "\n");
    exit(1);
}

$selectStmt = $pdo->prepare('SELECT title, seo_title, seo_description, seo_keywords FROM pages WHERE slug = ?');

foreach ($updates as $slug => $values) {
    $selectStmt->execute([$slug]);
    $current = $selectStmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        echo "[SKIP] {$slug}: page not found\n";
        continue;
    }

    $setClauses = [];
    $params = [];
    foreach (['title', 'seo_title', 'seo_description', 'seo_keywords'] as $field) {
        if (array_key_exists($field, $values)) {
            $setClauses[] = "{$field} = ?";
            $params[] = $values[$field];
        }
    }

    if (!$setClauses) {
        echo "[SKIP] {$slug}: nothing to update\n";
        continue;
    }

    $setClauses[] = 'updated_at = NOW()';
    $params[] = $slug;

    $sql = 'UPDATE pages SET ' . implode(', ', $setClauses) . ' WHERE slug = ?';
    $updateStmt = $pdo->prepare($sql);
    $updateStmt->execute($params);

    echo "[OK] {$slug}\n";
    foreach ($values as $field => $newValue) {
        $oldValue = $current[$field] ?? '(null)';
        echo "  {$field}: {$oldValue} --> {$newValue}\n";
    }
}

echo "\nDone.\n";
