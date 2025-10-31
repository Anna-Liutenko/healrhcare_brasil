# ЗАДАЧА: Реализовать UpdateCollectionCardImage Use Case

## КОНТЕКСТ
- Файл: backend/src/Application/UseCase/UpdateCollectionCardImage.php
- Язык: PHP 8.2
- Тип: UseCase (Application Layer)
- Зависимости: PageRepositoryInterface, BlockRepositoryInterface
- Зависит от меня: CollectionController, PATCH /api/pages/{id}/card-image

## СПЕЦИФИКАЦИЯ

### Входные параметры
- `$collectionPageId` (string): UUID коллекционной страницы (тип 'collection')
- `$targetPageId` (string): UUID страницы, для которой обновляется картинка
- `$imageUrl` (string): Новый URL картинки

### Выходные данные
- Тип: array
- Структура:
```json
{
  "success": true,
  "message": "Card image updated",
  "updatedCard": {
    "id": "uuid",
    "title": "...",
    "snippet": "...",
    "image": "/uploads/new-image.jpg",
    "url": "/slug",
    "type": "article",
    "publishedAt": "2025-10-20"
  }
}
```

### Алгоритм
1. Загрузить коллекционную страницу: $collection = $this->pageRepository->findById($collectionPageId)
   - Проверить, что страница существует и тип — 'collection'
   - Если нет — throw InvalidArgumentException
2. Проверить, что $targetPageId входит в коллекцию (и не в exclude)
   - $config = $collection->getCollectionConfig()
3. Загрузить целевую страницу: $targetPage = $this->pageRepository->findById($targetPageId)
   - Проверить, что страница существует
4. Обновить картинку: $targetPage->setCardImage($imageUrl)
   - Сохранить изменения через репозиторий
5. Собрать обновлённую карточку: $blocks = $this->blockRepository->findByPageId($targetPageId)
   - Формат: id, title, snippet, image, url, type, publishedAt
6. Вернуть результат: ['success' => true, 'message' => 'Card image updated', 'updatedCard' => [...]]

### Ограничения
- Использовать promoted properties, type hints
- Не использовать static, global, magic methods
- Исключить undefined variables, проверить все импорты
- Обработка ошибок: throw InvalidArgumentException при неверных данных

## ПРИМЕР ИСПОЛЬЗОВАНИЯ
```php
$useCase = new UpdateCollectionCardImage($pageRepo, $blockRepo);
$result = $useCase->execute('collection-uuid', 'target-uuid', '/uploads/new-image.jpg');
// $result = ['success' => true, 'message' => 'Card image updated', 'updatedCard' => [...]]
```

## ПРОВЕРКА РЕЗУЛЬТАТА
- Файл должен пройти `php -l`
- Импорты и методы должны существовать
- Логика соответствует спецификации
- Возвращаемый формат — как в примере
