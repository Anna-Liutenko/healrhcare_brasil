<?php

declare(strict_types=1);

namespace Presentation\Controller;

use Application\UseCase\GetAllStaticTemplates;
use Application\UseCase\ImportStaticTemplate;
use Infrastructure\Repository\FileSystemStaticTemplateRepository;
use Infrastructure\Parser\HtmlTemplateParser;
use Infrastructure\Repository\MySQLPageRepository;
use Infrastructure\Repository\MySQLBlockRepository;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLUserRepository;
use Infrastructure\Middleware\ApiLogger;
use Infrastructure\Auth\AuthHelper;
use Infrastructure\Auth\UnauthorizedException;

class TemplateController
{
    use \Presentation\Controller\JsonResponseTrait;

    public function index(): void
    {
        try {
            $templateRepo = new FileSystemStaticTemplateRepository();
            $useCase = new GetAllStaticTemplates($templateRepo);
            $templates = $useCase->execute();

            $out = array_map(function($t) {
                return [
                    'slug' => $t->getSlug(),
                    'title' => $t->getTitle(),
                    'filePath' => $t->getFilePath(),
                    'suggestedType' => $t->getSuggestedType()->value,
                    'isImported' => $t->isImported(),
                    'pageId' => $t->getPageId(),
                    'fileModifiedAt' => $t->getFileModifiedAt()->format('Y-m-d H:i:s')
                ];
            }, $templates);

            $this->jsonResponse(['success' => true, 'templates' => $out], 200);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => ['code' => 'SERVER_ERROR','message' => $e->getMessage()]], 500);
        }
    }

    public function import(string $slug): void
    {
        try {
            $templateRepo = new FileSystemStaticTemplateRepository();
            $pageRepo = new MySQLPageRepository();
            $blockRepo = new MySQLBlockRepository();
            $parser = new HtmlTemplateParser();

            $useCase = new ImportStaticTemplate($templateRepo, $pageRepo, $blockRepo, $parser);

            // Allow admin to request upsert via query param ?upsert=1 or ?upsert=true
            $upsert = isset($_GET['upsert']) && ($_GET['upsert'] === '1' || $_GET['upsert'] === 'true');

            // Centralized authorization
            $user = AuthHelper::requireAuth();

            $pageId = $useCase->execute($slug, $user->getId(), $upsert);

            // fetch page to return final slug
            $pageRepo = new MySQLPageRepository();
            $page = $pageRepo->findById($pageId);
            $this->jsonResponse([
                'success' => true,
                'pageId' => $pageId,
                'slug' => $page?->getSlug()
            ], 201);
        } catch (UnauthorizedException $e) {
            $this->jsonResponse(['success' => false, 'error' => ['code' => 'UNAUTHORIZED','message' => $e->getMessage()]], $e->getHttpCode());
        } catch (\InvalidArgumentException $e) {
            $this->jsonResponse(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR','message' => $e->getMessage()]], 400);
        } catch (\Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => ['code' => 'SERVER_ERROR','message' => $e->getMessage()]], 500);
        }
    }
}
