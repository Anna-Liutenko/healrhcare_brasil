<?php
declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\PageRepositoryInterface;

/**
 * Use Case: Обновить картинку карточки в коллекции
 */
class UpdateCollectionCardImage
{
    private PageRepositoryInterface $pageRepository;
    
    public function __construct(PageRepositoryInterface $pageRepository)
    {
        $this->pageRepository = $pageRepository;
    }
    
    /**
     * Выполнить: обновить картинку карточки
     * 
     * @param string $collectionPageId UUID страницы-коллекции
     * @param string $targetPageId UUID страницы, чью картинку меняем
     * @param string $imageUrl Новый URL картинки
     */
    public function execute(
        string $collectionPageId, 
        string $targetPageId, 
        string $imageUrl
    ): void {
        // 1. Загрузить страницу-коллекцию
        $collectionPage = $this->pageRepository->findById($collectionPageId);
        
        if (!$collectionPage || !$collectionPage->getType()->isCollection()) {
            throw new \InvalidArgumentException('Page is not a collection');
        }
        
        // 2. Обновить collectionConfig.cardImages[targetPageId]
        $config = $collectionPage->getCollectionConfig() ?? [];
        
        if (!isset($config['cardImages'])) {
            $config['cardImages'] = [];
        }
        
        $config['cardImages'][$targetPageId] = $imageUrl;
        
        // 3. Сохранить
        $collectionPage->setCollectionConfig($config);
        // Use the repository's public save() method to persist changes
        $this->pageRepository->save($collectionPage);
    }
}
