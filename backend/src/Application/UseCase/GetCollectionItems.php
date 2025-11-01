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
     * @param int $page Номер страницы (начиная с 1)
     * @param int $limit Количество элементов на странице
     * @return array Массив с секциями, карточками и мета-информацией о пагинации
     */
    public function execute(string $collectionPageId, ?string $sectionSlug = null, int $page = 1, int $limit = 12): array
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

        // Map section slug to page types (allow extension via config later)
        $sectionTypeMap = [
            'guides' => ['guide'],
            'articles' => ['article'],
            null => ['guide', 'article']
        ];

        if ($sectionSlug !== null && !isset($sectionTypeMap[$sectionSlug])) {
            throw new \InvalidArgumentException('Invalid section: ' . $sectionSlug);
        }

        $allowedTypes = $sectionTypeMap[$sectionSlug];
        
        // 3. Загрузить страницы, отфильтрованные по секции
        $allPages = [];
        foreach ($sourceTypes as $type) {
            // Skip types not allowed for this section
            if (!in_array($type, $allowedTypes, true)) {
                continue;
            }
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
        
        // 6. Применить пагинацию (offset/limit)
        $offset = ($page - 1) * $limit;
        $totalItems = count($allPages);
        $totalPages = $limit > 0 ? (int)ceil($totalItems / $limit) : 1;
        $paginatedPages = array_slice($allPages, $offset, $limit);

        // 7. Сформировать карточки (только для текущей страницы)
        $cards = [];
        foreach ($paginatedPages as $paginatedPage) {
            // Загрузить блоки для извлечения картинки
            $blocks = $this->blockRepository->findByPageId($paginatedPage->getId());

            $cards[] = [
                'id' => $paginatedPage->getId(),
                'title' => $paginatedPage->getTitle(),
                'snippet' => $paginatedPage->getSeoDescription() ?? '',
                'image' => $this->normalizeImageUrl($paginatedPage->getCardImage($blocks)),
                'url' => '/healthcare-cms-backend/public/p/' . $paginatedPage->getSlug(),
                'type' => $paginatedPage->getType()->value,
                'publishedAt' => $paginatedPage->getPublishedAt()?->format('Y-m-d H:i:s')
            ];
        }
        
        // 8. Если запрошена конкретная секция, вернуть её как единственную секцию
        if ($sectionSlug !== null) {
            $sectionTitle = $sectionSlug === 'guides' ? 'Гайды' : ($sectionSlug === 'articles' ? 'Статьи' : 'Все материалы');
            $result = [
                'sections' => [
                    [
                        'title' => $sectionTitle,
                        'items' => $cards
                    ]
                ]
            ];
        } else {
            // 8b. Группировать по секциям (если заданы) или вернуть одну секцию со всеми карточками
            if ($sections) {
                $result = $this->groupBySections($cards, $sections);
            } else {
                $result = [
                    'sections' => [
                        [
                            'title' => 'Все материалы',
                            'items' => $cards
                        ]
                    ]
                ];
            }
        }

        // 9. Добавить мета-информацию о пагинации
        $result['pagination'] = [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $limit,
            'hasNextPage' => $page < $totalPages,
            'hasPrevPage' => $page > 1,
            'currentSection' => $sectionSlug
        ];

        return $result;
    }
    
    /**
     * Нормализировать URL картинки
     * 
     * Конвертирует полные URL с localhost в относительные пути /uploads/
     * Это нужно, чтобы картинки, загруженные в админке, работали на публичной стороне
     * 
     * @param string $url URL картинки (может быть полный или относительный)
     * @return string Нормализированный URL (/uploads/... или /healthcare-cms-frontend/uploads/...)
     */
    private function normalizeImageUrl(string $url): string
    {
        if (empty($url)) {
            return $url;
        }
        
        // Конвертировать http://localhost/healthcare-cms-backend/public/uploads/... в /uploads/...
        $url = (string)preg_replace(
            '~^https?://localhost/healthcare-cms-backend/public(/uploads/.+)$~i',
            '$1',
            $url
        );
        
        return $url;
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
            
            // Здесь мы фильтруем карточки по типам секции
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
