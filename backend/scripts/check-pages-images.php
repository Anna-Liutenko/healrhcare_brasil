<?php
/**
 * Check Pages Images
 *
 * Scans all pages in database, extracts <img src> references from `rendered_html` (fallback to `content`),
 * verifies that each referenced file exists inside the uploads directory, and writes a JSON + CSV report.
 *
 * Usage:
 *   php backend/scripts/check-pages-images.php
 *   php backend/scripts/check-pages-images.php --uploads-dir="C:\xampp\htdocs\healthcare-cms-backend\public\uploads" --out=reports
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['uploads-dir::', 'out::', 'dry-run']);
$uploadsDir = $options['uploads-dir'] ?? (getenv('XAMPP_UPLOADS_PATH') ?: __DIR__ . '/../public/uploads');
$outDir = __DIR__ . '/' . ($options['out'] ?? 'reports');
$dryRun = array_key_exists('dry-run', $options);

if (!is_dir($outDir)) {
    if (!mkdir($outDir, 0775, true) && !is_dir($outDir)) {
        echo "Failed to create output directory: $outDir\n";
        exit(1);
    }
}

echo "Uploads dir: $uploadsDir\n";
echo "Report dir: $outDir\n";
if ($dryRun) echo "MODE: dry-run\n";

try {
    $pageRepo = new \Infrastructure\Repository\MySQLPageRepository();
    $pages = $pageRepo->findAll();
} catch (Throwable $e) {
    // Fallback: try direct DB query via Connection class
    if (class_exists('\Infrastructure\Database\Connection')) {
        $pdo = \Infrastructure\Database\Connection::getPDO();
        $stmt = $pdo->query("SELECT id, title, slug, rendered_html, content FROM pages");
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        throw $e;
    }
}

$report = [];
$totalPages = count($pages);
$missingFilesTotal = 0;

foreach ($pages as $p) {
    // $p may be an object (entity) or associative array depending on repo implementation
    if (is_object($p)) {
        $id = method_exists($p, 'getId') ? $p->getId() : ($p->id ?? null);
        $title = method_exists($p, 'getTitle') ? $p->getTitle() : ($p->title ?? '');
        $slug = method_exists($p, 'getSlug') ? $p->getSlug() : ($p->slug ?? '');
        $html = method_exists($p, 'getRenderedHtml') ? $p->getRenderedHtml() : ($p->rendered_html ?? null);
        $content = method_exists($p, 'getContent') ? $p->getContent() : ($p->content ?? null);
    } else {
        $id = $p['id'] ?? null;
        $title = $p['title'] ?? '';
        $slug = $p['slug'] ?? '';
        $html = $p['rendered_html'] ?? null;
        $content = $p['content'] ?? null;
    }

    $source = $html ?: $content ?: '';

    // Extract img src attributes
    $matches = [];
    preg_match_all('/<img[^>]+src=["\']?([^"\' >]+)["\']?/i', $source, $matches);
    $srcs = $matches[1] ?? [];

    $pageReport = [
        'id' => $id,
        'title' => $title,
        'slug' => $slug,
        'images' => [],
    ];

    foreach ($srcs as $src) {
        $status = 'skipped';
        $resolvedPath = null;

        // ignore data URIs
        if (strpos($src, 'data:') === 0) {
            $status = 'embedded';
        } else {
            // normalize: if src contains domain, parse path
            $u = @parse_url($src);
            $path = $u['path'] ?? $src;

            // if path contains /uploads or starts without http but points to uploads
            if (strpos($path, '/uploads') !== false || strpos($path, 'uploads/') === 0) {
                $filename = basename($path);
                $resolvedPath = rtrim($uploadsDir, "\\/") . DIRECTORY_SEPARATOR . $filename;
                if (file_exists($resolvedPath)) {
                    $status = 'ok';
                } else {
                    $status = 'missing';
                    $missingFilesTotal++;
                }
            } else {
                // external or other path — mark as external
                $status = 'external';
            }
        }

        $pageReport['images'][] = [
            'src' => $src,
            'status' => $status,
            'path' => $resolvedPath,
        ];
    }

    $report[] = $pageReport;
}

$jsonFile = $outDir . '/pages-images-report.json';
$csvFile = $outDir . '/pages-images-report.csv';
file_put_contents($jsonFile, json_encode(['meta'=>['scanned_at'=>date(DATE_ATOM),'total_pages'=>$totalPages,'missing_files_total'=>$missingFilesTotal],'pages'=>$report], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$fh = fopen($csvFile, 'w');
fputcsv($fh, ['page_id','slug','title','image_src','status','resolved_path']);
foreach ($report as $pr) {
    foreach ($pr['images'] as $img) {
        fputcsv($fh, [$pr['id'],$pr['slug'],$pr['title'],$img['src'],$img['status'],$img['path']]);
    }
}
fclose($fh);

echo "\nDone. JSON: $jsonFile\nCSV:  $csvFile\n";
echo "Total pages scanned: $totalPages\n";
echo "Total missing files referenced: $missingFilesTotal\n";

if ($missingFilesTotal > 0) {
    echo "\nPlease review the reports in $outDir to take action (re-upload, update page, or remove reference).\n";
}

exit(0);

