<?php
/**
 * Check Blocks Script
 * Проверяет наличие блоков в базе данных
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Infrastructure\Database\Connection;

echo "=== Healthcare CMS - Blocks Check ===\n\n";

try {
    $pdo = Connection::getInstance();
    
    // Получить все страницы
    echo "1. Список страниц:\n";
    echo str_repeat("-", 100) . "\n";
    printf("%-40s %-40s %-10s\n", "Page ID", "Title", "Blocks");
    echo str_repeat("-", 100) . "\n";
    
    $stmt = $pdo->query("
        SELECT p.id, p.title, COUNT(b.id) as block_count
        FROM pages p
        LEFT JOIN blocks b ON p.id = b.page_id
        GROUP BY p.id, p.title
        ORDER BY p.created_at DESC
    ");
    
    $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($pages)) {
        echo "   ⚠ Страниц не найдено!\n";
    } else {
        foreach ($pages as $page) {
            printf(
                "%-40s %-40s %-10s\n",
                substr($page['id'], 0, 36),
                substr($page['title'], 0, 40),
                $page['block_count']
            );
        }
    }
    
    echo str_repeat("-", 100) . "\n";
    echo "\nВсего страниц: " . count($pages) . "\n\n";
    
    // Показать детали блоков для последних 5 страниц
    echo "2. Детали блоков (последние 5 страниц):\n";
    echo str_repeat("=", 100) . "\n";
    
    $recentPages = array_slice($pages, 0, 5);
    
    foreach ($recentPages as $page) {
        echo "\nСтраница: " . $page['title'] . " (ID: " . substr($page['id'], 0, 8) . "...)\n";
        echo str_repeat("-", 100) . "\n";
        
        $stmt = $pdo->prepare("
            SELECT id, type, position, custom_name
            FROM blocks
            WHERE page_id = ?
            ORDER BY position
        ");
        $stmt->execute([$page['id']]);
        $blocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($blocks)) {
            echo "  ⚠ Блоков нет\n";
        } else {
            foreach ($blocks as $block) {
                printf(
                    "  [%d] %s (ID: %s) %s\n",
                    $block['position'],
                    $block['type'],
                    substr($block['id'], 0, 8) . '...',
                    $block['custom_name'] ? "- " . $block['custom_name'] : ''
                );
            }
        }
    }
    
    echo "\n" . str_repeat("=", 100) . "\n";
    echo "=== Проверка завершена ===\n";
    
} catch (PDOException $e) {
    echo "Ошибка БД: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
