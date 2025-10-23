<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\Entity\Page;
use Domain\Repository\PageRepositoryInterface;
use Domain\ValueObject\PageStatus;
use Domain\ValueObject\PageType;
use Infrastructure\Database\Connection;
use PDO;
use DateTime;

/**
 * MySQL Page Repository
 */
class MySQLPageRepository implements PageRepositoryInterface
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findById(string $id): ?Page
    {
        $stmt = $this->db->prepare('SELECT * FROM pages WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findBySlug(string $slug): ?Page
    {
        $stmt = $this->db->prepare('SELECT * FROM pages WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();

        // DEBUG: log slug lookup to help E2E diagnostics
        $dbg = [
            'time' => date('c'),
            'action' => 'findBySlug',
            'slug' => $slug,
            'row' => $row
        ];
        @file_put_contents(__DIR__ . '/../../../logs/e2e-findBySlug.log', json_encode($dbg, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND | LOCK_EX);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM pages ORDER BY created_at DESC');
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    /**
     * Convenience: Find pages by raw type string and status string
     * This bridges older code that uses string types instead of PageType/PageStatus objects
     *
     * @param string $type
     * @param string $status
     * @return array
     */
    public function findByTypeAndStatus(string $type, string $status): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pages WHERE type = :type AND status = :status ORDER BY published_at DESC');
        $stmt->execute(['type' => $type, 'status' => $status]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findByType(PageType $type, ?PageStatus $status = null): array
    {
        if ($status) {
            $stmt = $this->db->prepare('
                SELECT * FROM pages 
                WHERE type = :type AND status = :status 
                ORDER BY published_at DESC
            ');
            $stmt->execute([
                'type' => $type->value,
                'status' => $status->getValue()
            ]);
        } else {
            $stmt = $this->db->prepare('
                SELECT * FROM pages 
                WHERE type = :type 
                ORDER BY published_at DESC
            ');
            $stmt->execute(['type' => $type->value]);
        }

        $rows = $stmt->fetchAll();
        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findByStatus(PageStatus $status): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM pages 
            WHERE status = :status 
            ORDER BY updated_at DESC
        ');
        $stmt->execute(['status' => $status->getValue()]);
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findMenuPages(): array
    {
        $stmt = $this->db->query('
            SELECT * FROM pages 
            WHERE show_in_menu = 1 AND status = "published"
            ORDER BY menu_order ASC, title ASC
        ');
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function findTrashedPages(): array
    {
        $stmt = $this->db->query('
            SELECT * FROM pages 
            WHERE status = "trashed"
            ORDER BY trashed_at DESC
        ');
        $rows = $stmt->fetchAll();

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    public function save(Page $page): void
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM pages WHERE id = :id');
        $stmt->execute(['id' => $page->getId()]);
        $exists = $stmt->fetchColumn() > 0;

        if ($exists) {
            $this->update($page);
        } else {
            $this->insert($page);
        }
    }

    public function delete(string $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM pages WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function deleteOldTrashedPages(): int
    {
        $stmt = $this->db->prepare('
            DELETE FROM pages 
            WHERE status = "trashed" 
            AND trashed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ');
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function slugExists(string $slug, ?string $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = $this->db->prepare('
                SELECT COUNT(*) FROM pages 
                WHERE slug = :slug AND id != :excludeId
            ');
            $stmt->execute(['slug' => $slug, 'excludeId' => $excludeId]);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM pages WHERE slug = :slug');
            $stmt->execute(['slug' => $slug]);
        }

        return $stmt->fetchColumn() > 0;
    }

    // Private helper methods

    private function insert(Page $page): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO pages (
                id, title, slug, status, type,
                seo_title, seo_description, seo_keywords,
                show_in_menu, menu_title, show_in_sitemap, menu_order,
                collection_config, page_specific_code, card_image,
                created_at, updated_at, published_at, trashed_at,
                created_by, rendered_html, source_template_slug
            ) VALUES (
                :id, :title, :slug, :status, :type,
                :seo_title, :seo_description, :seo_keywords,
                :show_in_menu, :menu_title, :show_in_sitemap, :menu_order,
                :collection_config, :page_specific_code, :card_image,
                :created_at, :updated_at, :published_at, :trashed_at,
                :created_by, :rendered_html, :source_template_slug
            )
        ');

        $stmt->execute([
            'id' => $page->getId(),
            'title' => $page->getTitle(),
            'slug' => $page->getSlug(),
            'status' => $page->getStatus()->getValue(),
            'type' => $page->getType()->value,
            'seo_title' => $page->getSeoTitle(),
            'seo_description' => $page->getSeoDescription(),
            'seo_keywords' => $page->getSeoKeywords(),
            'show_in_menu' => $page->isShowInMenu() ? 1 : 0,
            'menu_title' => $page->getMenuTitle(),
            'show_in_sitemap' => $page->isShowInSitemap() ? 1 : 0,
            'menu_order' => $page->getMenuOrder(),
            'collection_config' => $page->getCollectionConfig() ? json_encode($page->getCollectionConfig()) : null,
            'page_specific_code' => $page->getPageSpecificCode(),
            'card_image' => $page->getCardImage(),
            'created_at' => $page->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $page->getUpdatedAt()->format('Y-m-d H:i:s'),
            'published_at' => $page->getPublishedAt()?->format('Y-m-d H:i:s'),
            'trashed_at' => $page->getTrashedAt()?->format('Y-m-d H:i:s'),
            'created_by' => $page->getCreatedBy(),
            'rendered_html' => $page->getRenderedHtml(),
            'source_template_slug' => $page->getSourceTemplateSlug()
        ]);
    }

    private function update(Page $page): void
    {
        $stmt = $this->db->prepare('
            UPDATE pages SET
                title = :title,
                slug = :slug,
                status = :status,
                type = :type,
                seo_title = :seo_title,
                seo_description = :seo_description,
                seo_keywords = :seo_keywords,
                show_in_menu = :show_in_menu,
                menu_title = :menu_title,
                show_in_sitemap = :show_in_sitemap,
                menu_order = :menu_order,
                collection_config = :collection_config,
                page_specific_code = :page_specific_code,
                card_image = :card_image,
                rendered_html = :rendered_html,
                updated_at = :updated_at,
                published_at = :published_at,
                trashed_at = :trashed_at
            WHERE id = :id
        ');

        $stmt->execute([
            'id' => $page->getId(),
            'title' => $page->getTitle(),
            'slug' => $page->getSlug(),
            'status' => $page->getStatus()->getValue(),
            'type' => $page->getType()->value,
            'seo_title' => $page->getSeoTitle(),
            'seo_description' => $page->getSeoDescription(),
            'seo_keywords' => $page->getSeoKeywords(),
            'show_in_menu' => $page->isShowInMenu() ? 1 : 0,
            'menu_title' => $page->getMenuTitle(),
            'show_in_sitemap' => $page->isShowInSitemap() ? 1 : 0,
            'menu_order' => $page->getMenuOrder(),
            'collection_config' => $page->getCollectionConfig() ? json_encode($page->getCollectionConfig()) : null,
            'page_specific_code' => $page->getPageSpecificCode(),
            'card_image' => $page->getCardImage(),
            'rendered_html' => $page->getRenderedHtml(),
            'updated_at' => $page->getUpdatedAt()->format('Y-m-d H:i:s'),
            'published_at' => $page->getPublishedAt()?->format('Y-m-d H:i:s'),
            'trashed_at' => $page->getTrashedAt()?->format('Y-m-d H:i:s'),
        ]);
    }

    private function hydrate(array $row): Page
    {
        $page = new Page(
            id: $row['id'],
            title: $row['title'],
            slug: $row['slug'],
            status: PageStatus::from($row['status']),
            type: PageType::from($row['type']),
            seoTitle: $row['seo_title'],
            seoDescription: $row['seo_description'],
            seoKeywords: $row['seo_keywords'],
            showInMenu: (bool)$row['show_in_menu'],
            showInSitemap: (bool)$row['show_in_sitemap'],
            menuOrder: (int)$row['menu_order'],
            createdAt: new DateTime($row['created_at']),
            updatedAt: new DateTime($row['updated_at']),
            publishedAt: $row['published_at'] ? new DateTime($row['published_at']) : null,
            trashedAt: $row['trashed_at'] ? new DateTime($row['trashed_at']) : null,
            createdBy: $row['created_by'],
            collectionConfig: $row['collection_config'] ? json_decode($row['collection_config'], true) : null,
            pageSpecificCode: $row['page_specific_code'],
            cardImage: $row['card_image'] ?? null
        );

        // Optional properties set via setters to avoid changing constructor signature
        if (array_key_exists('source_template_slug', $row)) {
            $page->setSourceTemplateSlug($row['source_template_slug']);
        }
        if (array_key_exists('rendered_html', $row)) {
            $page->setRenderedHtml($row['rendered_html']);
        }
        if (array_key_exists('menu_title', $row)) {
            $page->setMenuTitle($row['menu_title']);
        }

        return $page;
    }
}
