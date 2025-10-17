-- ============================================
-- МИГРАЦИЯ: Inline-редактирование + Управление страницами
-- Файл: 010_add_inline_editing_fields.sql
-- Дата: 2025-10-04
-- Описание: Добавляет поля для inline-редактирования,
--           управления видимостью и статусами страниц
-- ============================================

USE healthcare_cms;

-- ============================================
-- 1. ОБНОВЛЕНИЕ ТАБЛИЦЫ PAGES
-- ============================================

-- Добавить поле visibility (видимость страницы)
ALTER TABLE pages 
  ADD COLUMN visibility ENUM('public', 'unlisted', 'private') 
    DEFAULT 'public' 
    COMMENT 'Видимость: public=всем, unlisted=по ссылке, private=только авторизованным'
    AFTER status;

-- Добавить поле archived_at (дата архивирования)
ALTER TABLE pages 
  ADD COLUMN archived_at DATETIME NULL 
    COMMENT 'Дата и время архивирования страницы'
    AFTER published_at;

-- Добавить поле last_edited_by (кто последний редактировал)
ALTER TABLE pages 
  ADD COLUMN last_edited_by CHAR(36) NULL 
    COMMENT 'UUID пользователя, который последним редактировал страницу'
    AFTER created_by;

-- Обновить enum status (добавить статус 'scheduled')
ALTER TABLE pages 
  MODIFY COLUMN status ENUM('draft', 'published', 'archived', 'scheduled') 
    DEFAULT 'draft'
    COMMENT 'Статус страницы: draft=черновик, published=опубликовано, archived=архив, scheduled=запланировано';

-- Добавить foreign key для last_edited_by
ALTER TABLE pages 
  ADD CONSTRAINT fk_pages_last_edited_by 
    FOREIGN KEY (last_edited_by) 
    REFERENCES users(id) 
    ON DELETE SET NULL;

-- ============================================
-- 2. ОБНОВЛЕНИЕ ТАБЛИЦЫ BLOCKS
-- ============================================

-- Добавить поле is_editable (можно ли редактировать inline)
ALTER TABLE blocks 
  ADD COLUMN is_editable BOOLEAN 
    DEFAULT TRUE 
    COMMENT 'Можно ли редактировать блок в inline-режиме';

-- Добавить поле editable_fields (какие поля редактируемые)
ALTER TABLE blocks 
  ADD COLUMN editable_fields JSON NULL 
    COMMENT 'Массив путей к редактируемым полям, например: ["data.title", "data.text", "data.image"]';

-- ============================================
-- 3. ИНДЕКСЫ ДЛЯ ПРОИЗВОДИТЕЛЬНОСТИ
-- ============================================

-- Индекс для фильтрации по видимости
CREATE INDEX idx_pages_visibility 
  ON pages(visibility);

-- Индекс для фильтрации архивных страниц
CREATE INDEX idx_pages_archived_at 
  ON pages(archived_at);

-- Индекс для поиска страниц по редактору
CREATE INDEX idx_pages_last_edited_by 
  ON pages(last_edited_by);

-- Составной индекс для фильтрации по статусу и видимости
CREATE INDEX idx_pages_status_visibility 
  ON pages(status, visibility);

-- ============================================
-- 4. ОБНОВЛЕНИЕ СУЩЕСТВУЮЩИХ ДАННЫХ
-- ============================================

-- Установить visibility = 'public' для всех существующих страниц
UPDATE pages 
  SET visibility = 'public' 
  WHERE visibility IS NULL;

-- Установить is_editable = TRUE для всех существующих блоков
UPDATE blocks 
  SET is_editable = TRUE 
  WHERE is_editable IS NULL;

-- ============================================
-- 5. ПРИМЕРЫ ЗНАЧЕНИЙ editable_fields
-- ============================================

-- Пример для блока main-screen
UPDATE blocks 
  SET editable_fields = JSON_ARRAY(
    'data.title',
    'data.text',
    'data.backgroundImage',
    'data.buttonText',
    'data.buttonLink'
  )
  WHERE type = 'main-screen' AND editable_fields IS NULL;

-- Пример для блока text-block
UPDATE blocks 
  SET editable_fields = JSON_ARRAY(
    'data.title',
    'data.text'
  )
  WHERE type = 'text-block' AND editable_fields IS NULL;

-- Пример для блока service-cards
UPDATE blocks 
  SET editable_fields = JSON_ARRAY(
    'data.title',
    'data.cards[*].title',
    'data.cards[*].description',
    'data.cards[*].icon'
  )
  WHERE type = 'service-cards' AND editable_fields IS NULL;

-- Пример для блока article-cards
UPDATE blocks 
  SET editable_fields = JSON_ARRAY(
    'data.title',
    'data.cards[*].title',
    'data.cards[*].excerpt',
    'data.cards[*].image',
    'data.cards[*].link'
  )
  WHERE type = 'article-cards' AND editable_fields IS NULL;

-- Пример для блока about-section
UPDATE blocks 
  SET editable_fields = JSON_ARRAY(
    'data.title',
    'data.text',
    'data.image'
  )
  WHERE type = 'about-section' AND editable_fields IS NULL;

-- Пример для блока page-header
UPDATE blocks 
  SET editable_fields = JSON_ARRAY(
    'data.title',
    'data.subtitle'
  )
  WHERE type = 'page-header' AND editable_fields IS NULL;

-- Пример для блока cta-section
UPDATE blocks 
  SET editable_fields = JSON_ARRAY(
    'data.title',
    'data.text',
    'data.buttonText',
    'data.buttonLink'
  )
  WHERE type = 'cta-section' AND editable_fields IS NULL;

-- Пример для блока faq-block
UPDATE blocks 
  SET editable_fields = JSON_ARRAY(
    'data.title',
    'data.items[*].question',
    'data.items[*].answer'
  )
  WHERE type = 'faq-block' AND editable_fields IS NULL;

-- ============================================
-- 6. ПРОВЕРКА РЕЗУЛЬТАТОВ
-- ============================================

-- Посмотреть структуру таблицы pages
DESCRIBE pages;

-- Посмотреть структуру таблицы blocks
DESCRIBE blocks;

-- Посмотреть индексы на таблице pages
SHOW INDEX FROM pages;

-- Посмотреть пример страницы с новыми полями
SELECT 
  id, 
  title, 
  status, 
  visibility, 
  archived_at, 
  last_edited_by 
FROM pages 
LIMIT 1;

-- Посмотреть пример блока с editable_fields
SELECT 
  id, 
  type, 
  is_editable, 
  editable_fields 
FROM blocks 
WHERE editable_fields IS NOT NULL 
LIMIT 1;

-- ============================================
-- ROLLBACK (на случай если нужно откатить)
-- ============================================

/*
-- Удалить новые индексы
DROP INDEX idx_pages_visibility ON pages;
DROP INDEX idx_pages_archived_at ON pages;
DROP INDEX idx_pages_last_edited_by ON pages;
DROP INDEX idx_pages_status_visibility ON pages;

-- Удалить foreign key
ALTER TABLE pages DROP FOREIGN KEY fk_pages_last_edited_by;

-- Удалить новые поля из pages
ALTER TABLE pages DROP COLUMN visibility;
ALTER TABLE pages DROP COLUMN archived_at;
ALTER TABLE pages DROP COLUMN last_edited_by;

-- Вернуть старый enum status
ALTER TABLE pages 
  MODIFY COLUMN status ENUM('draft', 'published', 'archived') 
    DEFAULT 'draft';

-- Удалить новые поля из blocks
ALTER TABLE blocks DROP COLUMN is_editable;
ALTER TABLE blocks DROP COLUMN editable_fields;
*/

-- ============================================
-- КОНЕЦ МИГРАЦИИ
-- ============================================
