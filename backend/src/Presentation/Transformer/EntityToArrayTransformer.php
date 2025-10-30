<?php

declare(strict_types=1);

namespace Presentation\Transformer;

use Domain\Entity\Page;
use Domain\Entity\Block;
use Domain\Entity\User;
use Domain\Entity\MediaFile;

class EntityToArrayTransformer
{
    /**
     * Convert Page entity to array for JSON response
     * ALL keys MUST be camelCase
     */
    public static function pageToArray(Page $page, bool $includeBlocks = false): array
    {
        $result = [
            'id' => $page->getId(),
            'title' => $page->getTitle(),
            'slug' => $page->getSlug(),
            'status' => $page->getStatus()->getValue(),
            'type' => $page->getType()->value,

            // ✅ camelCase (НЕ show_in_menu!)
            'showInMenu' => $page->isShowInMenu(),
            'showInSitemap' => $page->isShowInSitemap(),
            'menuOrder' => $page->getMenuOrder(),
            'menuTitle' => $page->getMenuTitle(),

            // SEO fields
            'seoTitle' => $page->getSeoTitle(),
            'seoDescription' => $page->getSeoDescription(),
            'seoKeywords' => $page->getSeoKeywords(),

            // ✅ camelCase (НЕ created_by!)
            'createdBy' => $page->getCreatedBy(),
            'createdAt' => $page->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $page->getUpdatedAt()->format('Y-m-d H:i:s'),
            'publishedAt' => $page->getPublishedAt()?->format('Y-m-d H:i:s'),

            // Optional fields
            'collectionConfig' => $page->getCollectionConfig(),
            'pageSpecificCode' => $page->getPageSpecificCode(),
            'sourceTemplateSlug' => $page->getSourceTemplateSlug(),
            'renderedHtml' => $page->getRenderedHtml(),
        ];

        if ($includeBlocks && method_exists($page, 'getBlocks')) {
            $result['blocks'] = array_map(
                [self::class, 'blockToArray'],
                $page->getBlocks()
            );
        }

        return $result;
    }

    /**
     * Convert Block entity to array for JSON response
     */
    public static function blockToArray(Block $block): array
    {
        return [
            'id' => $block->getId(),
            'pageId' => $block->getPageId(),      // ✅ camelCase!
            'type' => $block->getType(),
            'position' => $block->getPosition(),
            'customName' => $block->getCustomName(), // ✅ camelCase!
            'clientId' => $block->getClientId(),    // ✅ camelCase!
            'data' => $block->getData(),
            'createdAt' => $block->getCreatedAt()->format('Y-m-d H:i:s'),
            'updatedAt' => $block->getUpdatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Convert User entity to array for JSON response
     */
    public static function userToArray(User $user): array
    {
        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'role' => $user->getRole(),
            'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
            // НЕ включать password hash!
        ];
    }

    /**
     * Convert MediaFile entity to array for JSON response
     */
    public static function mediaFileToArray(MediaFile $file): array
    {
        return [
            'id' => $file->getId(),
            'filename' => $file->getFilename(),
            'originalFilename' => $file->getOriginalFilename(),
            'url' => $file->getUrl(),
            'type' => $file->getType(),
            'size' => $file->getSize(),
            'uploadedBy' => $file->getUploadedBy(),  // ✅ camelCase!
            'uploadedAt' => $file->getUploadedAt()->format('Y-m-d H:i:s'),  // ✅ camelCase!
        ];
    }
}