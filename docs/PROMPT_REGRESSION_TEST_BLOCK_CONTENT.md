# Промпт: Регрессионный PHPUnit тест для проверки сохранения блоков с ключом `content`

## Контекст и предыстория

В процессе разработки E2E-теста для workflow редактирования страницы выяснилось, что клиентский код (тесты и некоторые frontend-приложения) отправляет payload блоков под ключом `content`, а не `data`. Это привело к тому, что блоки сохранялись с пустыми данными (`data = []`), так как бэкенд ожидал ключ `data`.

### Что было исправлено
- В `backend/src/Presentation/Controller/PageController.php` (метод `create`) добавлена поддержка обоих ключей: если `content` присутствует, он используется как `data` блока.
- В `backend/src/Application/UseCase/UpdatePage.php` (метод `execute`) аналогично добавлена поддержка `content`.

### Зачем нужен регрессионный тест
Чтобы предотвратить возврат проблемы в будущем (если кто-то случайно удалит эту логику или изменит контракт), нужен автоматический тест, который:
1. Создаёт страницу с блоками, используя ключ `content` в payload.
2. Проверяет, что данные блоков корректно сохранились в БД.
3. Обновляет страницу с новыми блоками (также используя `content`).
4. Проверяет, что обновлённые данные корректно сохранились.

---

## Задача

Создай регрессионный **unit/integration тест** в PHPUnit, который проверяет корректность сохранения блоков при использовании ключа `content` вместо `data`.

### Требования к тесту

#### 1. Расположение и именование
- Файл теста: `backend/tests/Integration/BlockContentKeyRegressionTest.php`
- Класс: `Tests\Integration\BlockContentKeyRegressionTest`
- Метод теста: `testBlocksAreSavedCorrectlyWithContentKey`

#### 2. Что тест должен проверять

**Сценарий создания страницы:**
1. Использовать `CreatePage` use-case напрямую или через репозитории для создания страницы.
2. Сохранить несколько блоков (минимум 2: один `text`, один `hero`) с payload под ключом `content`:
   ```php
   [
       'type' => 'text',
       'position' => 0,
       'content' => ['text' => 'Sample text content']
   ],
   [
       'type' => 'hero',
       'position' => 1,
       'content' => ['heading' => 'Sample Hero', 'subheading' => 'Subtitle']
   ]
   ```
3. После сохранения, загрузить эти блоки из БД через `MySQLBlockRepository->findByPageId()`.
4. Проверить (`assert`), что:
   - Количество блоков соответствует отправленному (2).
   - Для каждого блока `getData()` возвращает массив с корректными данными:
     - Text-блок: `['text' => 'Sample text content']`
     - Hero-блок: `['heading' => 'Sample Hero', 'subheading' => 'Subtitle']`

**Сценарий обновления страницы:**
1. Использовать `UpdatePage` use-case для замены блоков страницы новыми блоками (также с ключом `content`):
   ```php
   [
       'type' => 'text',
       'position' => 0,
       'content' => ['text' => 'Updated text content']
   ]
   ```
2. Загрузить блоки из БД после обновления.
3. Проверить:
   - Количество блоков изменилось (теперь 1 блок).
   - Данные блока соответствуют обновлённому payload: `['text' => 'Updated text content']`.

#### 3. Окружение теста
- Использовать SQLite in-memory БД или файл-базу из `backend/tests/tmp/` (как в E2E-тестах).
- Использовать PHPUnit bootstrap (`backend/tests/_bootstrap.php`), который инжектирует sqlite PDO в `Connection::getInstance()`.
- Тест должен быть изолированным: очищать данные до/после теста или использовать транзакции (если доступно).

#### 4. Структура теста (псевдокод)

```php
<?php
declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Application\UseCase\CreatePage;
use Application\UseCase\UpdatePage;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Domain\Entity\Block;
use Ramsey\Uuid\Uuid;

class BlockContentKeyRegressionTest extends TestCase
{
    private MySQLPageRepository $pageRepo;
    private MySQLBlockRepository $blockRepo;

    protected function setUp(): void
    {
        // Инициализация репозиториев (sqlite PDO уже инжектирован в bootstrap)
        $this->pageRepo = new MySQLPageRepository();
        $this->blockRepo = new MySQLBlockRepository();
    }

    public function testBlocksAreSavedCorrectlyWithContentKey(): void
    {
        // === ЧАСТЬ 1: Создание страницы с блоками (используя 'content') ===
        
        $pageData = [
            'title' => 'Regression Test Page',
            'slug' => 'regression-test-' . time(),
            'type' => 'regular',
            'status' => 'draft',
        ];

        $createUseCase = new CreatePage($this->pageRepo);
        $page = $createUseCase->execute($pageData);
        $pageId = $page->getId();

        // Сохраняем блоки вручную (имитируя логику PageController::create с ключом 'content')
        $blocksPayload = [
            [
                'type' => 'text',
                'position' => 0,
                'content' => ['text' => 'Sample text content'] // <-- используем 'content'
            ],
            [
                'type' => 'hero',
                'position' => 1,
                'content' => ['heading' => 'Sample Hero', 'subheading' => 'Subtitle']
            ]
        ];

        foreach ($blocksPayload as $index => $blockData) {
            // Логика из PageController::create — поддержка 'content' и 'data'
            $blockPayload = [];
            if (isset($blockData['data']) && is_array($blockData['data'])) {
                $blockPayload = $blockData['data'];
            } elseif (isset($blockData['content']) && is_array($blockData['content'])) {
                $blockPayload = $blockData['content'];
            }

            $block = new Block(
                id: Uuid::uuid4()->toString(),
                pageId: $pageId,
                type: $blockData['type'],
                position: $blockData['position'] ?? $index,
                data: $blockPayload
            );
            $this->blockRepo->save($block);
        }

        // Загружаем блоки из БД
        $savedBlocks = $this->blockRepo->findByPageId($pageId);

        // Проверяем количество
        $this->assertCount(2, $savedBlocks, 'Should save 2 blocks');

        // Проверяем данные text-блока
        $textBlock = $savedBlocks[0];
        $this->assertSame('text', $textBlock->getType());
        $this->assertSame(['text' => 'Sample text content'], $textBlock->getData());

        // Проверяем данные hero-блока
        $heroBlock = $savedBlocks[1];
        $this->assertSame('hero', $heroBlock->getType());
        $this->assertSame(['heading' => 'Sample Hero', 'subheading' => 'Subtitle'], $heroBlock->getData());

        // === ЧАСТЬ 2: Обновление блоков страницы (используя 'content') ===

        $updatePayload = [
            'blocks' => [
                [
                    'type' => 'text',
                    'position' => 0,
                    'content' => ['text' => 'Updated text content']
                ]
            ]
        ];

        $updateUseCase = new UpdatePage($this->pageRepo, $this->blockRepo);
        $updateUseCase->execute($pageId, $updatePayload);

        // Загружаем обновлённые блоки
        $updatedBlocks = $this->blockRepo->findByPageId($pageId);

        // Проверяем количество (теперь 1 блок)
        $this->assertCount(1, $updatedBlocks, 'Should have 1 block after update');

        // Проверяем данные обновлённого блока
        $updatedTextBlock = $updatedBlocks[0];
        $this->assertSame('text', $updatedTextBlock->getType());
        $this->assertSame(['text' => 'Updated text content'], $updatedTextBlock->getData());
    }

    protected function tearDown(): void
    {
        // Очистка тестовых данных (опционально, если нужна изоляция между тестами)
        // Например, можно удалить все страницы со slug начинающимся с 'regression-test-'
    }
}
```

#### 5. Ожидаемое поведение теста
- Тест должен **пройти** (зелёный) если логика обработки `content` работает корректно.
- Тест должен **упасть** (красный) если кто-то удалит логику поддержки ключа `content` в контроллере или use-case.

#### 6. Как запустить тест
```powershell
cd backend
& 'C:\xampp\php\php.exe' vendor\bin\phpunit tests/Integration/BlockContentKeyRegressionTest.php
```

---

## Дополнительные требования и рекомендации

### Покрытие edge-cases
Дополнительно можно создать тест-кейсы для:
1. **Только `data` без `content`** — должен работать как обычно.
2. **Оба ключа `data` и `content` присутствуют** — приоритет у `data` (или определить явное поведение).
3. **Пустой `content`** — блок сохраняется с пустым массивом.

### Интеграция в CI
- Добавить этот тест в CI pipeline (например, GitHub Actions) чтобы он запускался автоматически при каждом коммите.
- Рекомендуется группировать его с другими интеграционными тестами в отдельный test suite.

### Документация контракта API
После написания теста рекомендуется обновить документацию API (`docs/API_CONTRACT.md` или аналогичный файл) с явным описанием:
- Блоки можно отправлять с ключом `data` или `content`.
- Оба ключа взаимозаменяемы; если оба присутствуют, используется `data` (или `content` — в зависимости от выбранной стратегии).

---

## Ожидаемый результат

После выполнения задачи получим:
1. Файл `backend/tests/Integration/BlockContentKeyRegressionTest.php` с полностью рабочим тестом.
2. Тест проходит локально при запуске через PHPUnit.
3. Код теста покрывает создание и обновление блоков с ключом `content`.
4. Тест защищает от регрессий в будущем — если логика поддержки `content` будет удалена, тест упадёт.

---

(Промпт создан для использования в разработке; содержит все детали для самостоятельной реализации или передачи другому разработчику/AI-агенту)
