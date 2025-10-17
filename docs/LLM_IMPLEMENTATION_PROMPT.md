# Промпт для LLM: Пошаговая реализация Clean Architecture

**Дата создания:** 16 октября 2025  
**Цель:** Детальные инструкции для LLM по внедрению чистой архитектуры в Healthcare CMS  
**Основа:** NEXT_STEPS_PLAN.md + CLEAN_ARCHITECTURE_VIOLATIONS_ANALYSIS.md  
**Формат:** Копировать блок → отправить в LLM → получить результат → проверить

---

## 📖 Как использовать этот документ

1. **Копируй блок полностью** — от заголовка "ПРОМПТ #X" до следующего заголовка
2. **Вставляй в LLM** — ChatGPT, Claude, или другую модель
3. **Проверяй результат** — используй чек-лист в каждом блоке
4. **Переходи к следующему** — только после успешной проверки

**Важно:** Каждый промпт самодостаточен и содержит весь необходимый контекст.

---

## 🔥 ПРОМПТ #0: Quick Fix — Исправление данных (30-60 минут)

```
# Задача: Восстановить работу inline-редактора

## Контекст проблемы
В Healthcare CMS при попытке редактирования блока возникает ошибка:
- HTTP 500: "Block not found"
- Frontend отправляет blockId = f34cac9d-b426-4b22-887a-3a194f06eba1
- В БД для страницы 9c23c3ff-1e2f-44fa-880f-c92b66a63257 существуют 4 ДРУГИХ блока:
  * 1537c131-bf2d-4c99-910c-4f7f346e5264
  * ca9a0c45-33d4-4f95-a208-d7cb4ada95fb
  * 3e1e89b2-cfd8-401c-aef5-94fbde91907f
  * b87ff61a-974b-4dbb-a005-24ea2dbcf5e7

## Твоя задача
Помоги найти и исправить причину рассинхрона данных.

## Шаги выполнения

### Шаг 1: Найти где frontend получает blockId
Выполни поиск в коде:
```bash
# Команды для выполнения
grep -r "data-block-id" frontend/
grep -r "blockId" frontend/js/InlineEditorManager.js
```

Проанализируй результаты и скажи:
- Откуда берётся blockId?
- Генерируется ли он динамически или берётся из атрибутов HTML?

### Шаг 2: Проверить HTML рендеринг страницы
Найди код, который рендерит публичную страницу:
- Файл: backend/src/Presentation/Controller/PublicPageController.php
- Метод: show(string $slug)

Проверь:
- Откуда берутся блоки для рендеринга?
- Есть ли генерация атрибутов data-block-id?
- Совпадают ли ID блоков в HTML с ID в БД?

### Шаг 3: Проверить откуда пришли текущие блоки
Составь SQL-запрос для проверки:
```sql
SELECT id, type, position, created_at, updated_at
FROM blocks 
WHERE page_id = '9c23c3ff-1e2f-44fa-880f-c92b66a63257'
ORDER BY position;
```

Дай мне этот запрос для выполнения.

### Шаг 4: Предложить решение
На основе анализа предложи один из вариантов:

**Вариант A:** Если HTML содержит неправильные ID
- Найди где генерируется HTML с data-block-id
- Исправь логику, чтобы использовались правильные ID из БД

**Вариант B:** Если блоки были неправильно импортированы
- Предложи переимпортировать страницу через Templates → Import
- Или предоставь SQL для удаления старых и создания новых блоков

**Вариант C:** Если проблема в кешировании
- Найди где может кешироваться HTML страницы
- Предложи как очистить кеш

## Ожидаемый результат
- Конкретное место в коде, где возникает проблема
- Чёткие инструкции по исправлению
- SQL-запросы или код для применения (если нужно)

## Чек-лист проверки
- [ ] Найдено где frontend берёт blockId
- [ ] Найдено где backend генерирует HTML с data-block-id
- [ ] Выявлена точная причина рассинхрона
- [ ] Предложено рабочее решение
- [ ] Решение можно применить за 10-30 минут
```

**Чек-лист после выполнения:**
- [ ] LLM нашла причину рассинхрона
- [ ] Получено решение (код или SQL)
- [ ] Решение применено
- [ ] Inline-редактор работает

---

## 📦 ПРОМПТ #1.1: Проверка Domain интерфейсов (2 часа)

```
# Задача: Создать/проверить интерфейсы репозиториев в Domain слое

## Контекст проекта
- Проект: Healthcare CMS на PHP
- Архитектура: Clean Architecture (Domain, Application, Infrastructure, Presentation)
- БД: MySQL
- Репозитории: MySQLPageRepository, MySQLBlockRepository, MySQLUserRepository и др.

## Твоя задача
Проверить наличие 7 интерфейсов репозиториев в Domain слое. Если отсутствуют — создать.

## Список необходимых интерфейсов

1. PageRepositoryInterface
2. BlockRepositoryInterface
3. UserRepositoryInterface
4. SessionRepositoryInterface
5. MediaRepositoryInterface
6. MenuRepositoryInterface
7. SettingsRepositoryInterface

## Шаги выполнения

### Шаг 1: Проверка существующих файлов
Проверь наличие файлов в папке `backend/src/Domain/Repository/`:
```bash
ls -la backend/src/Domain/Repository/
```

Для каждого интерфейса проверь:
- Существует ли файл?
- Правильно ли объявлен namespace?
- Есть ли все необходимые методы?

### Шаг 2: Создание интерфейсов (если отсутствуют)

Используй следующий шаблон для каждого интерфейса:

**Файл: backend/src/Domain/Repository/PageRepositoryInterface.php**
```php
<?php

declare(strict_types=1);

namespace Domain\Repository;

use Domain\Entity\Page;

/**
 * Интерфейс репозитория для работы со страницами
 */
interface PageRepositoryInterface
{
    /**
     * Найти страницу по ID
     * @param string $id UUID страницы
     * @return Page|null
     */
    public function findById(string $id): ?Page;

    /**
     * Найти страницу по slug
     * @param string $slug Человекочитаемый URL
     * @return Page|null
     */
    public function findBySlug(string $slug): ?Page;

    /**
     * Получить все страницы
     * @return Page[] Массив объектов Page
     */
    public function findAll(): array;

    /**
     * Сохранить страницу (создание или обновление)
     * @param Page $page Объект страницы
     * @return void
     */
    public function save(Page $page): void;

    /**
     * Удалить страницу по ID
     * @param string $id UUID страницы
     * @return void
     */
    public function delete(string $id): void;

    /**
     * Обновить статус страницы
     * @param string $id UUID страницы
     * @param string $status Новый статус (draft, published, archived)
     * @return void
     */
    public function updateStatus(string $id, string $status): void;
}
```

**Файл: backend/src/Domain/Repository/BlockRepositoryInterface.php**
```php
<?php

declare(strict_types=1);

namespace Domain\Repository;

use Domain\Entity\Block;

/**
 * Интерфейс репозитория для работы с блоками
 */
interface BlockRepositoryInterface
{
    /**
     * Найти блок по ID
     * @param string $id UUID блока
     * @return Block|null
     */
    public function findById(string $id): ?Block;

    /**
     * Найти все блоки страницы
     * @param string $pageId UUID страницы
     * @return Block[] Массив блоков, отсортированных по position
     */
    public function findByPageId(string $pageId): array;

    /**
     * Сохранить блок (создание или обновление)
     * @param Block $block Объект блока
     * @return void
     */
    public function save(Block $block): void;

    /**
     * Удалить блок по ID
     * @param string $id UUID блока
     * @return void
     */
    public function delete(string $id): void;

    /**
     * Удалить все блоки страницы
     * @param string $pageId UUID страницы
     * @return void
     */
    public function deleteByPageId(string $pageId): void;
}
```

**Для остальных интерфейсов:** Создай аналогично, адаптируя методы под сущность:
- UserRepositoryInterface (findById, findByEmail, save, delete)
- SessionRepositoryInterface (findById, findByUserId, save, delete, deleteExpired)
- MediaRepositoryInterface (findById, findAll, save, delete)
- MenuRepositoryInterface (findById, findAll, save, delete, reorder)
- SettingsRepositoryInterface (get, set, delete)

### Шаг 3: Проверка реализации интерфейсов

Для каждого MySQL репозитория проверь объявление класса:

**Пример:** `backend/src/Infrastructure/Repository/MySQLPageRepository.php`
```php
<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\Repository\PageRepositoryInterface;
use Domain\Entity\Page;

class MySQLPageRepository implements PageRepositoryInterface
{
    // ... методы реализации
}
```

Убедись что:
- ✅ Класс объявляет `implements PageRepositoryInterface`
- ✅ Все методы интерфейса реализованы
- ✅ Сигнатуры методов совпадают с интерфейсом

### Шаг 4: Проверка синтаксиса

Для каждого созданного файла запусти проверку:
```bash
php -l backend/src/Domain/Repository/PageRepositoryInterface.php
php -l backend/src/Infrastructure/Repository/MySQLPageRepository.php
```

## Ожидаемый результат

После выполнения должны существовать:
- 7 файлов интерфейсов в `backend/src/Domain/Repository/`
- Все MySQL репозитории реализуют соответствующие интерфейсы
- Нет синтаксических ошибок

## Чек-лист проверки
- [ ] Созданы все 7 интерфейсов
- [ ] Каждый интерфейс содержит все необходимые методы
- [ ] Все MySQL репозитории объявляют implements
- [ ] php -l не выдаёт ошибок
- [ ] PHPStan (если установлен) не выдаёт ошибок типизации
```

**Чек-лист после выполнения:**
- [ ] Все 7 интерфейсов созданы
- [ ] MySQL репозитории реализуют интерфейсы
- [ ] Нет ошибок синтаксиса
- [ ] Можно переходить к Промпту #1.2

---

## 🚨 ПРОМПТ #1.2: Создание Domain Exceptions (1 час)

```
# Задача: Создать типизированные исключения с контекстом для отладки

## Контекст
В текущем коде Use Cases выбрасывают generic InvalidArgumentException без контекста.
Нужно создать специализированные Domain Exceptions, которые содержат информацию для отладки.

## Твоя задача
Создать 2 обязательных и 1 опциональное Domain Exception.

## Файлы для создания

### 1. BlockNotFoundException

**Файл: backend/src/Domain/Exception/BlockNotFoundException.php**
```php
<?php

declare(strict_types=1);

namespace Domain\Exception;

use DomainException;

/**
 * Исключение выбрасывается когда блок не найден
 */
class BlockNotFoundException extends DomainException
{
    private string $blockId;
    private string $pageId;

    /**
     * Создать исключение для несуществующего блока
     * 
     * @param string $blockId UUID блока, который не найден
     * @param string $pageId UUID страницы, где искали блок
     * @return self
     */
    public static function forBlockId(string $blockId, string $pageId): self
    {
        $exception = new self(
            sprintf(
                'Block with ID "%s" not found on page "%s"',
                $blockId,
                $pageId
            )
        );
        
        $exception->blockId = $blockId;
        $exception->pageId = $pageId;
        
        return $exception;
    }

    /**
     * Получить контекст ошибки для логирования/отладки
     * 
     * @return array{blockId: string, pageId: string}
     */
    public function getContext(): array
    {
        return [
            'blockId' => $this->blockId,
            'pageId' => $this->pageId,
        ];
    }

    /**
     * Получить ID несуществующего блока
     * 
     * @return string
     */
    public function getBlockId(): string
    {
        return $this->blockId;
    }

    /**
     * Получить ID страницы
     * 
     * @return string
     */
    public function getPageId(): string
    {
        return $this->pageId;
    }
}
```

### 2. PageNotFoundException

**Файл: backend/src/Domain/Exception/PageNotFoundException.php**
```php
<?php

declare(strict_types=1);

namespace Domain\Exception;

use DomainException;

/**
 * Исключение выбрасывается когда страница не найдена
 */
class PageNotFoundException extends DomainException
{
    private string $pageId;
    private ?string $slug = null;

    /**
     * Создать исключение для несуществующей страницы (по ID)
     * 
     * @param string $pageId UUID страницы
     * @return self
     */
    public static function forPageId(string $pageId): self
    {
        $exception = new self(
            sprintf('Page with ID "%s" not found', $pageId)
        );
        
        $exception->pageId = $pageId;
        
        return $exception;
    }

    /**
     * Создать исключение для несуществующей страницы (по slug)
     * 
     * @param string $slug Человекочитаемый URL
     * @return self
     */
    public static function forSlug(string $slug): self
    {
        $exception = new self(
            sprintf('Page with slug "%s" not found', $slug)
        );
        
        $exception->pageId = '';
        $exception->slug = $slug;
        
        return $exception;
    }

    /**
     * Получить контекст ошибки
     * 
     * @return array{pageId: string, slug: string|null}
     */
    public function getContext(): array
    {
        return [
            'pageId' => $this->pageId,
            'slug' => $this->slug,
        ];
    }

    public function getPageId(): string
    {
        return $this->pageId;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }
}
```

### 3. UnauthorizedException (опционально)

**Файл: backend/src/Domain/Exception/UnauthorizedException.php**
```php
<?php

declare(strict_types=1);

namespace Domain\Exception;

use DomainException;

/**
 * Исключение выбрасывается при попытке неавторизованного доступа
 */
class UnauthorizedException extends DomainException
{
    private ?string $userId = null;
    private string $resource;

    public static function forResource(string $resource, ?string $userId = null): self
    {
        $message = $userId 
            ? sprintf('User "%s" is not authorized to access resource "%s"', $userId, $resource)
            : sprintf('Unauthorized access to resource "%s"', $resource);
        
        $exception = new self($message);
        $exception->resource = $resource;
        $exception->userId = $userId;
        
        return $exception;
    }

    public function getContext(): array
    {
        return [
            'resource' => $this->resource,
            'userId' => $this->userId,
        ];
    }
}
```

## Тестирование созданных исключений

Создай простой тест-скрипт для проверки:

**Файл: backend/tests/manual/test_exceptions.php**
```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Domain\Exception\BlockNotFoundException;
use Domain\Exception\PageNotFoundException;

// Тест 1: BlockNotFoundException
try {
    throw BlockNotFoundException::forBlockId(
        'f34cac9d-b426-4b22-887a-3a194f06eba1',
        '9c23c3ff-1e2f-44fa-880f-c92b66a63257'
    );
} catch (BlockNotFoundException $e) {
    echo "✅ BlockNotFoundException работает:\n";
    echo "   Сообщение: " . $e->getMessage() . "\n";
    echo "   Контекст: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n\n";
}

// Тест 2: PageNotFoundException
try {
    throw PageNotFoundException::forPageId('9c23c3ff-1e2f-44fa-880f-c92b66a63257');
} catch (PageNotFoundException $e) {
    echo "✅ PageNotFoundException работает:\n";
    echo "   Сообщение: " . $e->getMessage() . "\n";
    echo "   Контекст: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n\n";
}

// Тест 3: PageNotFoundException по slug
try {
    throw PageNotFoundException::forSlug('about-us');
} catch (PageNotFoundException $e) {
    echo "✅ PageNotFoundException (slug) работает:\n";
    echo "   Сообщение: " . $e->getMessage() . "\n";
    echo "   Контекст: " . json_encode($e->getContext(), JSON_PRETTY_PRINT) . "\n\n";
}

echo "✅ Все Domain Exceptions работают корректно!\n";
```

Запусти тест:
```bash
php backend/tests/manual/test_exceptions.php
```

## Ожидаемый результат

После выполнения:
- Созданы 2-3 файла с Domain Exceptions
- Каждое исключение extends DomainException
- Каждое исключение имеет метод getContext()
- Тест-скрипт успешно выполняется

## Чек-лист проверки
- [ ] BlockNotFoundException создан и работает
- [ ] PageNotFoundException создан и работает
- [ ] Метод getContext() возвращает правильные данные
- [ ] Тест-скрипт выполняется без ошибок
- [ ] php -l не выдаёт ошибок синтаксиса
```

**Чек-лист после выполнения:**
- [ ] 2-3 Domain Exceptions созданы
- [ ] Тест-скрипт подтверждает работу
- [ ] Можно переходить к Промпту #1.3

---

## 🔧 ПРОМПТ #1.3: Создание DI Container (2-3 часа)

```
# Задача: Реализовать простой DI Container для управления зависимостями

## Контекст
Нужен DI Container который умеет:
1. Регистрировать зависимости (bind, singleton)
2. Разрешать зависимости (get)
3. Создавать объекты с автоинжекцией (make)

## Твоя задача
Создать файл `backend/src/Infrastructure/Container/Container.php` с полной реализацией.

## Код Container

**Файл: backend/src/Infrastructure/Container/Container.php**
```php
<?php

declare(strict_types=1);

namespace Infrastructure\Container;

use Exception;
use ReflectionClass;
use ReflectionParameter;

/**
 * Простой DI Container с поддержкой autowiring
 */
class Container
{
    /**
     * Зарегистрированные bindings (создаются каждый раз)
     * @var array<string, callable>
     */
    private array $bindings = [];

    /**
     * Зарегистрированные singletons (создаются один раз)
     * @var array<string, callable>
     */
    private array $singletons = [];

    /**
     * Кеш созданных singleton экземпляров
     * @var array<string, object>
     */
    private array $instances = [];

    /**
     * Зарегистрировать binding (новый экземпляр каждый раз)
     * 
     * @param string $abstract Имя класса или интерфейса
     * @param callable $factory Фабричная функция
     * @return void
     */
    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /**
     * Зарегистрировать singleton (один экземпляр на весь контейнер)
     * 
     * @param string $abstract Имя класса или интерфейса
     * @param callable $factory Фабричная функция
     * @return void
     */
    public function singleton(string $abstract, callable $factory): void
    {
        $this->singletons[$abstract] = $factory;
    }

    /**
     * Получить экземпляр зарегистрированного класса
     * 
     * @param string $abstract Имя класса или интерфейса
     * @return object
     * @throws Exception Если класс не зарегистрирован
     */
    public function get(string $abstract): object
    {
        // Если singleton уже создан — вернуть из кеша
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Если зарегистрирован как singleton — создать и закешировать
        if (isset($this->singletons[$abstract])) {
            $instance = $this->singletons[$abstract]($this);
            $this->instances[$abstract] = $instance;
            return $instance;
        }

        // Если зарегистрирован как binding — создать новый
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]($this);
        }

        throw new Exception(
            sprintf('Class "%s" is not registered in container', $abstract)
        );
    }

    /**
     * Создать экземпляр класса с автоматической инжекцией зависимостей
     * 
     * @param string $class Имя класса для создания
     * @return object
     * @throws Exception Если класс не существует или зависимости не могут быть разрешены
     */
    public function make(string $class): object
    {
        $reflection = new ReflectionClass($class);

        // Если класса нет конструктора — создать без параметров
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        // Получить параметры конструктора
        $parameters = $constructor->getParameters();
        
        // Разрешить все зависимости
        $dependencies = $this->resolveDependencies($parameters);

        // Создать экземпляр с разрешёнными зависимостями
        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Разрешить массив зависимостей (параметров конструктора)
     * 
     * @param ReflectionParameter[] $parameters
     * @return array
     * @throws Exception Если зависимость не может быть разрешена
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            // Если нет типа — не можем разрешить
            if ($type === null) {
                throw new Exception(
                    sprintf(
                        'Cannot resolve parameter "%s" without type hint',
                        $parameter->getName()
                    )
                );
            }

            // Получить имя типа
            $typeName = $type->getName();

            // Если это встроенный тип (string, int, etc.) — не можем разрешить
            if ($type->isBuiltin()) {
                throw new Exception(
                    sprintf(
                        'Cannot resolve built-in type "%s" for parameter "%s"',
                        $typeName,
                        $parameter->getName()
                    )
                );
            }

            // Попытаться получить из контейнера
            try {
                $dependencies[] = $this->get($typeName);
            } catch (Exception $e) {
                // Если не зарегистрирован — попытаться создать рекурсивно
                try {
                    $dependencies[] = $this->make($typeName);
                } catch (Exception $makeException) {
                    throw new Exception(
                        sprintf(
                            'Cannot resolve dependency "%s" for class. ' .
                            'Make sure it is registered in container or can be auto-created.',
                            $typeName
                        ),
                        0,
                        $makeException
                    );
                }
            }
        }

        return $dependencies;
    }

    /**
     * Проверить зарегистрирован ли класс
     * 
     * @param string $abstract
     * @return bool
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) 
            || isset($this->singletons[$abstract])
            || isset($this->instances[$abstract]);
    }
}
```

## Тестирование Container

Создай тест-скрипт:

**Файл: backend/tests/manual/test_container.php**
```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Infrastructure\Container\Container;

// Создаём тестовые классы
class TestService {
    public function getName(): string { return 'TestService'; }
}

class TestRepository {
    public function getData(): string { return 'Data from repo'; }
}

class TestController {
    public function __construct(
        private TestService $service,
        private TestRepository $repository
    ) {}

    public function run(): void {
        echo "Service: " . $this->service->getName() . "\n";
        echo "Repository: " . $this->repository->getData() . "\n";
    }
}

// Тесты
$container = new Container();

echo "=== Тест 1: Регистрация и получение singleton ===\n";
$container->singleton(TestService::class, fn() => new TestService());
$service1 = $container->get(TestService::class);
$service2 = $container->get(TestService::class);
assert($service1 === $service2, 'Singleton должен возвращать тот же экземпляр');
echo "✅ Singleton работает: " . $service1->getName() . "\n\n";

echo "=== Тест 2: Регистрация и получение binding ===\n";
$container->bind(TestRepository::class, fn() => new TestRepository());
$repo1 = $container->get(TestRepository::class);
$repo2 = $container->get(TestRepository::class);
assert($repo1 !== $repo2, 'Binding должен создавать новый экземпляр');
echo "✅ Binding работает: " . $repo1->getData() . "\n\n";

echo "=== Тест 3: Autowiring (make) ===\n";
$controller = $container->make(TestController::class);
echo "✅ Autowiring работает:\n";
$controller->run();
echo "\n";

echo "=== Тест 4: Проверка has() ===\n";
assert($container->has(TestService::class), 'has() должен вернуть true для зарегистрированного');
assert(!$container->has('NonExistentClass'), 'has() должен вернуть false для незарегистрированного');
echo "✅ Метод has() работает\n\n";

echo "✅ Все тесты Container пройдены успешно!\n";
```

Запусти тест:
```bash
php backend/tests/manual/test_container.php
```

## Ожидаемый результат

- Container создан и работает
- bind() регистрирует зависимости (новый экземпляр каждый раз)
- singleton() регистрирует зависимости (один экземпляр)
- get() возвращает зарегистрированные объекты
- make() создаёт объекты с автоинжекцией
- Тест-скрипт выполняется без ошибок

## Чек-лист проверки
- [ ] Файл Container.php создан
- [ ] Все методы реализованы (bind, singleton, get, make, has)
- [ ] Тест-скрипт выполняется успешно
- [ ] Нет ошибок синтаксиса
- [ ] Autowiring работает (make создаёт объекты с зависимостями)
```

**Чек-лист после выполнения:**
- [ ] Container.php создан и работает
- [ ] Тесты подтверждают функциональность
- [ ] Можно переходить к Промпту #1.4

---

## 📋 ПРОМПТ #1.4: Создание bootstrap/container.php (2-3 часа)

```
# Задача: Создать централизованную конфигурацию всех зависимостей

## Контекст
У нас есть:
- DI Container (создан в предыдущем шаге)
- 7 репозиториев (MySQLPageRepository, MySQLBlockRepository, и др.)
- Use Cases (UpdatePageInline, GetPageWithBlocks, и др.)
- Services (MarkdownConverter, HTMLSanitizer)

Нужно зарегистрировать все зависимости в одном месте.

## Твоя задача
Создать файл `backend/bootstrap/container.php` который:
1. Создаёт экземпляр Container
2. Регистрирует все репозитории (как singleton)
3. Регистрирует сервисы (как singleton)
4. Регистрирует Use Cases (как bind)
5. Возвращает настроенный $container

## Код bootstrap/container.php

**Файл: backend/bootstrap/container.php**
```php
<?php

declare(strict_types=1);

use Infrastructure\Container\Container;

// Domain Repository Interfaces
use Domain\Repository\PageRepositoryInterface;
use Domain\Repository\BlockRepositoryInterface;
use Domain\Repository\UserRepositoryInterface;
use Domain\Repository\SessionRepositoryInterface;
use Domain\Repository\MediaRepositoryInterface;
use Domain\Repository\MenuRepositoryInterface;
use Domain\Repository\SettingsRepositoryInterface;

// Infrastructure MySQL Implementations
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Infrastructure\Repository\MySQLUserRepository;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLMediaRepository;
use Infrastructure\Repository\MySQLMenuRepository;
use Infrastructure\Repository\MySQLSettingsRepository;

// Application Use Cases
use Application\UseCase\UpdatePageInline;
use Application\UseCase\GetPageWithBlocks;
use Application\UseCase\GetAllPages;
use Application\UseCase\PublishPage;
use Application\UseCase\CreatePage;
use Application\UseCase\UpdatePage;
use Application\UseCase\DeletePage;

// Infrastructure Services
use Infrastructure\MarkdownConverter;
use Infrastructure\HTMLSanitizer;

// Создаём контейнер
$container = new Container();

// ========================================
// REPOSITORIES (Singleton - один экземпляр на весь запрос)
// ========================================

$container->singleton(PageRepositoryInterface::class, function() {
    return new MySQLPageRepository();
});

$container->singleton(BlockRepositoryInterface::class, function() {
    return new MySQLBlockRepository();
});

$container->singleton(UserRepositoryInterface::class, function() {
    return new MySQLUserRepository();
});

$container->singleton(SessionRepositoryInterface::class, function() {
    return new MySQLSessionRepository();
});

$container->singleton(MediaRepositoryInterface::class, function() {
    return new MySQLMediaRepository();
});

$container->singleton(MenuRepositoryInterface::class, function() {
    return new MySQLMenuRepository();
});

$container->singleton(SettingsRepositoryInterface::class, function() {
    return new MySQLSettingsRepository();
});

// ========================================
// SERVICES (Singleton)
// ========================================

$container->singleton(MarkdownConverter::class, function() {
    return new MarkdownConverter();
});

$container->singleton(HTMLSanitizer::class, function() {
    return new HTMLSanitizer();
});

// ========================================
// USE CASES (Bind - новый экземпляр каждый раз)
// ========================================

$container->bind(UpdatePageInline::class, function(Container $c) {
    return new UpdatePageInline(
        $c->get(PageRepositoryInterface::class),
        $c->get(BlockRepositoryInterface::class),
        $c->get(MarkdownConverter::class),
        $c->get(HTMLSanitizer::class)
    );
});

$container->bind(GetPageWithBlocks::class, function(Container $c) {
    return new GetPageWithBlocks(
        $c->get(PageRepositoryInterface::class),
        $c->get(BlockRepositoryInterface::class)
    );
});

$container->bind(GetAllPages::class, function(Container $c) {
    return new GetAllPages(
        $c->get(PageRepositoryInterface::class)
    );
});

$container->bind(PublishPage::class, function(Container $c) {
    return new PublishPage(
        $c->get(PageRepositoryInterface::class),
        // Добавь другие зависимости если нужно
    );
});

$container->bind(CreatePage::class, function(Container $c) {
    return new CreatePage(
        $c->get(PageRepositoryInterface::class)
    );
});

$container->bind(UpdatePage::class, function(Container $c) {
    return new UpdatePage(
        $c->get(PageRepositoryInterface::class),
        $c->get(BlockRepositoryInterface::class)
    );
});

$container->bind(DeletePage::class, function(Container $c) {
    return new DeletePage(
        $c->get(PageRepositoryInterface::class),
        $c->get(BlockRepositoryInterface::class)
    );
});

// Возвращаем настроенный контейнер
return $container;
```

## Smoke-тест для проверки

**Файл: backend/tests/manual/test_bootstrap_container.php**
```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

// Загружаем контейнер
$container = require __DIR__ . '/../../bootstrap/container.php';

echo "=== Тест bootstrap/container.php ===\n\n";

// Тест 1: Получить репозитории
echo "Тест 1: Получение репозиториев\n";
try {
    $pageRepo = $container->get(\Domain\Repository\PageRepositoryInterface::class);
    echo "✅ PageRepository: " . get_class($pageRepo) . "\n";
    
    $blockRepo = $container->get(\Domain\Repository\BlockRepositoryInterface::class);
    echo "✅ BlockRepository: " . get_class($blockRepo) . "\n";
    
    $userRepo = $container->get(\Domain\Repository\UserRepositoryInterface::class);
    echo "✅ UserRepository: " . get_class($userRepo) . "\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 2: Проверка singleton
echo "Тест 2: Проверка singleton для репозиториев\n";
try {
    $repo1 = $container->get(\Domain\Repository\PageRepositoryInterface::class);
    $repo2 = $container->get(\Domain\Repository\PageRepositoryInterface::class);
    
    if ($repo1 === $repo2) {
        echo "✅ Singleton работает: один экземпляр репозитория\n";
    } else {
        echo "❌ Singleton НЕ работает: разные экземпляры\n";
    }
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 3: Получить сервисы
echo "Тест 3: Получение сервисов\n";
try {
    $markdownConverter = $container->get(\Infrastructure\MarkdownConverter::class);
    echo "✅ MarkdownConverter: " . get_class($markdownConverter) . "\n";
    
    $sanitizer = $container->get(\Infrastructure\HTMLSanitizer::class);
    echo "✅ HTMLSanitizer: " . get_class($sanitizer) . "\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 4: Получить Use Cases
echo "Тест 4: Получение Use Cases\n";
try {
    $useCase1 = $container->get(\Application\UseCase\UpdatePageInline::class);
    echo "✅ UpdatePageInline: " . get_class($useCase1) . "\n";
    
    $useCase2 = $container->get(\Application\UseCase\GetPageWithBlocks::class);
    echo "✅ GetPageWithBlocks: " . get_class($useCase2) . "\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 5: Проверка bind (новый экземпляр)
echo "Тест 5: Проверка bind для Use Cases\n";
try {
    $uc1 = $container->get(\Application\UseCase\GetAllPages::class);
    $uc2 = $container->get(\Application\UseCase\GetAllPages::class);
    
    if ($uc1 !== $uc2) {
        echo "✅ Bind работает: разные экземпляры Use Case\n";
    } else {
        echo "⚠️  Bind возвращает тот же экземпляр (возможно это OK)\n";
    }
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
echo "\n";

echo "✅ Все тесты bootstrap/container.php завершены!\n";
```

Запусти smoke-тест:
```bash
php backend/tests/manual/test_bootstrap_container.php
```

## Ожидаемый результат

- Файл bootstrap/container.php создан
- Все 7 репозиториев зарегистрированы
- Сервисы зарегистрированы
- Use Cases зарегистрированы
- Smoke-тест выполняется успешно
- Можно загрузить контейнер: `$container = require 'bootstrap/container.php';`

## Чек-лист проверки
- [ ] bootstrap/container.php создан
- [ ] Все 7 репозиториев зарегистрированы как singleton
- [ ] Сервисы зарегистрированы
- [ ] Use Cases зарегистрированы как bind
- [ ] Smoke-тест выполняется без ошибок
- [ ] $container->get(PageRepositoryInterface::class) возвращает MySQLPageRepository
```

**Чек-лист после выполнения:**
- [ ] bootstrap/container.php создан
- [ ] Smoke-тест подтверждает работу
- [ ] Можно переходить к Промпту #1.5

---

## 📝 ПРОМПТ #1.5: Создание DTO классов (2 часа)

```
# Задача: Создать типобезопасные Request/Response DTO для UpdatePageInline

## Контекст
Текущий UseCase принимает 4 отдельных параметра:
```php
public function execute(string $pageId, string $blockId, string $fieldPath, string $newMarkdown): array
```

Нужно создать DTO (Data Transfer Object) для типобезопасной передачи данных.

## Твоя задача
Создать 2 DTO класса: Request и Response.

## Файлы для создания

### 1. UpdatePageInlineRequest

**Файл: backend/src/Application/DTO/UpdatePageInlineRequest.php**
```php
<?php

declare(strict_types=1);

namespace Application\DTO;

use InvalidArgumentException;

/**
 * Request DTO для UpdatePageInline Use Case
 */
final class UpdatePageInlineRequest
{
    /**
     * @param string $pageId UUID страницы
     * @param string $blockId UUID блока для обновления
     * @param string $fieldPath Путь к полю в JSON (например: "data.paragraphs[0]")
     * @param string $newMarkdown Новое содержимое в Markdown формате
     */
    public function __construct(
        private readonly string $pageId,
        private readonly string $blockId,
        private readonly string $fieldPath,
        private readonly string $newMarkdown
    ) {
        $this->validate();
    }

    /**
     * Валидация входных данных
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if (empty($this->pageId)) {
            throw new InvalidArgumentException('pageId cannot be empty');
        }

        if (empty($this->blockId)) {
            throw new InvalidArgumentException('blockId cannot be empty');
        }

        if (empty($this->fieldPath)) {
            throw new InvalidArgumentException('fieldPath cannot be empty');
        }

        // newMarkdown может быть пустым (удаление контента)
        
        // Проверка формата UUID (опционально, но рекомендуется)
        if (!$this->isValidUuid($this->pageId)) {
            throw new InvalidArgumentException('pageId must be a valid UUID');
        }

        if (!$this->isValidUuid($this->blockId)) {
            throw new InvalidArgumentException('blockId must be a valid UUID');
        }

        // Проверка формата fieldPath
        if (!preg_match('/^[a-zA-Z0-9_.\[\]]+$/', $this->fieldPath)) {
            throw new InvalidArgumentException(
                'fieldPath contains invalid characters'
            );
        }
    }

    /**
     * Простая проверка формата UUID
     */
    private function isValidUuid(string $uuid): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        );
    }

    // Геттеры
    public function getPageId(): string
    {
        return $this->pageId;
    }

    public function getBlockId(): string
    {
        return $this->blockId;
    }

    public function getFieldPath(): string
    {
        return $this->fieldPath;
    }

    public function getNewMarkdown(): string
    {
        return $this->newMarkdown;
    }

    /**
     * Создать из массива (удобно для создания из HTTP request)
     * 
     * @param array $data Массив с ключами: pageId, blockId, fieldPath, newMarkdown
     * @return self
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        return new self(
            pageId: $data['pageId'] ?? '',
            blockId: $data['blockId'] ?? '',
            fieldPath: $data['fieldPath'] ?? '',
            newMarkdown: $data['newMarkdown'] ?? ''
        );
    }

    /**
     * Преобразовать в массив
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'pageId' => $this->pageId,
            'blockId' => $this->blockId,
            'fieldPath' => $this->fieldPath,
            'newMarkdown' => $this->newMarkdown,
        ];
    }
}
```

### 2. UpdatePageInlineResponse

**Файл: backend/src/Application/DTO/UpdatePageInlineResponse.php**
```php
<?php

declare(strict_types=1);

namespace Application\DTO;

/**
 * Response DTO для UpdatePageInline Use Case
 */
final class UpdatePageInlineResponse
{
    /**
     * @param bool $success Успешно ли выполнена операция
     * @param string $blockId ID обновлённого блока
     * @param string $fieldPath Путь к обновлённому полю
     * @param string|null $convertedHtml HTML версия нового контента (опционально)
     */
    public function __construct(
        private readonly bool $success,
        private readonly string $blockId,
        private readonly string $fieldPath,
        private readonly ?string $convertedHtml = null
    ) {}

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getBlockId(): string
    {
        return $this->blockId;
    }

    public function getFieldPath(): string
    {
        return $this->fieldPath;
    }

    public function getConvertedHtml(): ?string
    {
        return $this->convertedHtml;
    }

    /**
     * Преобразовать в массив (для JSON response)
     * 
     * @return array
     */
    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
            'blockId' => $this->blockId,
            'fieldPath' => $this->fieldPath,
        ];

        if ($this->convertedHtml !== null) {
            $result['convertedHtml'] = $this->convertedHtml;
        }

        return $result;
    }

    /**
     * Фабричный метод для успешного результата
     * 
     * @param string $blockId
     * @param string $fieldPath
     * @param string|null $convertedHtml
     * @return self
     */
    public static function success(
        string $blockId,
        string $fieldPath,
        ?string $convertedHtml = null
    ): self {
        return new self(
            success: true,
            blockId: $blockId,
            fieldPath: $fieldPath,
            convertedHtml: $convertedHtml
        );
    }
}
```

## Тестирование DTO

**Файл: backend/tests/manual/test_dto.php**
```php
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Application\DTO\UpdatePageInlineRequest;
use Application\DTO\UpdatePageInlineResponse;

echo "=== Тест DTO классов ===\n\n";

// Тест 1: Создание Request
echo "Тест 1: Создание UpdatePageInlineRequest\n";
try {
    $request = new UpdatePageInlineRequest(
        pageId: '9c23c3ff-1e2f-44fa-880f-c92b66a63257',
        blockId: 'f34cac9d-b426-4b22-887a-3a194f06eba1',
        fieldPath: 'data.paragraphs[0]',
        newMarkdown: '# Test content'
    );
    
    echo "✅ Request создан:\n";
    echo "   PageId: " . $request->getPageId() . "\n";
    echo "   BlockId: " . $request->getBlockId() . "\n";
    echo "   FieldPath: " . $request->getFieldPath() . "\n";
    echo "   Markdown: " . $request->getNewMarkdown() . "\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 2: Создание Request из массива
echo "Тест 2: Создание Request::fromArray()\n";
try {
    $request = UpdatePageInlineRequest::fromArray([
        'pageId' => '9c23c3ff-1e2f-44fa-880f-c92b66a63257',
        'blockId' => 'f34cac9d-b426-4b22-887a-3a194f06eba1',
        'fieldPath' => 'data.title',
        'newMarkdown' => 'New title'
    ]);
    
    echo "✅ Request создан из массива\n";
    echo "   Array: " . json_encode($request->toArray(), JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 3: Валидация (негативный кейс)
echo "Тест 3: Валидация Request (должна выброситься ошибка)\n";
try {
    $request = new UpdatePageInlineRequest(
        pageId: '',  // пустой pageId
        blockId: 'f34cac9d-b426-4b22-887a-3a194f06eba1',
        fieldPath: 'data.title',
        newMarkdown: 'Test'
    );
    echo "❌ Валидация НЕ сработала (ожидалось исключение)\n";
} catch (InvalidArgumentException $e) {
    echo "✅ Валидация работает: " . $e->getMessage() . "\n";
}
echo "\n";

// Тест 4: Создание Response
echo "Тест 4: Создание UpdatePageInlineResponse\n";
$response = UpdatePageInlineResponse::success(
    blockId: 'f34cac9d-b426-4b22-887a-3a194f06eba1',
    fieldPath: 'data.paragraphs[0]',
    convertedHtml: '<p>Converted HTML</p>'
);

echo "✅ Response создан:\n";
echo "   Success: " . ($response->isSuccess() ? 'true' : 'false') . "\n";
echo "   Array: " . json_encode($response->toArray(), JSON_PRETTY_PRINT) . "\n";
echo "\n";

echo "✅ Все тесты DTO завершены!\n";
```

Запусти тест:
```bash
php backend/tests/manual/test_dto.php
```

## Ожидаемый результат

- 2 DTO класса созданы
- Request имеет валидацию
- Response имеет фабричный метод success()
- Оба DTO имеют методы toArray() для сериализации
- Request имеет fromArray() для десериализации
- Тест-скрипт выполняется успешно

## Чек-лист проверки
- [ ] UpdatePageInlineRequest создан
- [ ] UpdatePageInlineResponse создан
- [ ] Валидация в Request работает
- [ ] Методы fromArray() и toArray() работают
- [ ] Тест-скрипт выполняется без ошибок
- [ ] php -l не выдаёт ошибок синтаксиса
```

**Чек-лист после выполнения:**
- [ ] DTO классы созданы и протестированы
- [ ] Фаза 1 (Infrastructure) завершена
- [ ] Можно переходить к Фазе 2 (Use Cases)

---

## 📌 Навигация по промптам

Это первая часть промптов (Фаза 1 — Infrastructure). 

**Следующие промпты:**
- Промпт #2.1 — Рефакторинг UpdatePageInline
- Промпт #2.2 — Тесты для UpdatePageInline
- Промпт #2.3 — Рефакторинг других Use Cases
- Промпт #3.1 — Рефакторинг PageController
- Промпт #3.2 — Обновление index.php
- И т.д.

**Когда переходить к следующей части:**
После успешного завершения всех промптов Фазы 1 (чек-листы пройдены).

---

**Версия документа:** 1.0  
**Дата последнего обновления:** 16 октября 2025  
**Готовность:** ✅ Готово к использованию  
**Следующий шаг:** Скопировать Промпт #0 и отправить в LLM
