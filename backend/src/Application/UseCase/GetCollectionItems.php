<?php
declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;

/**
 * Use Case: Получить элементы коллекции
 * 
 * Собирает список страниц (статей/гайдов) для отображения на странице-коллекции
 */
class GetCollectionItems
{
    private PageRepositoryInterface $pageRepository;
    private BlockRepositoryInterface $blockRepository;
    
    public function __construct(
        PageRepositoryInterface $pageRepository,
        BlockRepositoryInterface $blockRepository
    ) {
        $this->pageRepository = $pageRepository;
        $this->blockRepository = $blockRepository;
    }
    
    /**
     * Выполнить: получить элементы коллекции
     * 
     * @param string $collectionPageId UUID страницы-коллекции
     * @return array Массив с секциями и карточками
     */
    public function execute(string $collectionPageId): array
    {
        // 1. Загрузить страницу-коллекцию
        $collectionPage = $this->pageRepository->findById($collectionPageId);
        
        if (!$collectionPage || !$collectionPage->getType()->isCollection()) {
            throw new \InvalidArgumentException('Page is not a collection');
        }
        
        // 2. Прочитать конфигурацию
        $config = $collectionPage->getCollectionConfig() ?? [];
        $sourceTypes = $config['sourceTypes'] ?? ['article', 'guide'];
        $sortBy = $config['sortBy'] ?? 'publishedAt';
        $sortOrder = $config['sortOrder'] ?? 'desc';
        $sections = $config['sections'] ?? null;
        $excludePages = $config['excludePages'] ?? [];
        $cardImages = $config['cardImages'] ?? [];
        
        // 3. Загрузить все опубликованные страницы нужных типов
        $allPages = [];
        foreach ($sourceTypes as $type) {
            $pages = $this->pageRepository->findByTypeAndStatus($type, 'published');
            $allPages = array_merge($allPages, $pages);
        }
        
        // 4. Исключить страницы из excludePages
        $allPages = array_filter($allPages, function($page) use ($excludePages) {
            return !in_array($page->getId(), $excludePages);
        });
        
        // 5. Сортировать страницы
        usort($allPages, function($a, $b) use ($sortBy, $sortOrder) {
            $aValue = $this->getPageFieldValue($a, $sortBy);
            $bValue = $this->getPageFieldValue($b, $sortBy);
            
            $comparison = $aValue <=> $bValue;
            return $sortOrder === 'desc' ? -$comparison : $comparison;
        });
        
        // 6. Сформировать карточки
        $cards = [];
        foreach ($allPages as $page) {
            // Загрузить блоки для извлечения картинки
            $blocks = $this->blockRepository->findByPageId($page->getId());
            
            $cards[] = [
                'id' => $page->getId(),
                'title' => $page->getTitle(),
                'snippet' => $page->getSeoDescription() ?? '',
                'image' => $page->getCardImage($blocks),
                'url' => '/' . $page->getSlug(),
                'type' => $page->getType()->value,
                'publishedAt' => $page->getPublishedAt()?->format('Y-m-d H:i:s')
            ];
        }
        
        // 7. Группировать по секциям (если заданы)
        if ($sections) {
            return $this->groupBySections($cards, $sections);
        }
        
        // 8. Вернуть одну секцию со всеми карточками
        return [
            'sections' => [
                [
                    'title' => 'Все материалы',
                    'items' => $cards
                ]
            ]
        ];
    }
    
    /**
     * Получить значение поля страницы для сортировки
     */
    private function getPageFieldValue($page, string $field)
    {
        switch ($field) {
            case 'publishedAt':
                return $page->getPublishedAt()?->getTimestamp() ?? 0;
            case 'createdAt':
                return $page->getCreatedAt()->getTimestamp();
            case 'updatedAt':
                return $page->getUpdatedAt()?->getTimestamp() ?? 0;
            case 'title':
                return $page->getTitle();
            default:
                return 0;
        }
    }
    
    /**
     * Группировать карточки по секциям
     */
    private function groupBySections(array $cards, array $sections): array
    {
        $result = ['sections' => []];
        
        foreach ($sections as $section) {
            $sectionTitle = $section['title'] ?? 'Без названия';
            $sectionTypes = $section['sourceTypes'] ?? [];
            
            // Фильтровать карточки по типам секции
            $sectionCards = array_filter($cards, function($card) use ($sectionTypes) {
                return in_array($card['type'], $sectionTypes);
            });
            
            $result['sections'][] = [
                'title' => $sectionTitle,
                'items' => array_values($sectionCards)
            ];
        }
        
        return $result;
    }
}
