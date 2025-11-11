<?php
/**
 * Quick Fix Script - Nov 10, 2025
 * Fixes critical issues found in diagnostic report
 */

echo "\n========================================\n";
echo "Quick Fix Script - Healthcare CMS\n";
echo "========================================\n\n";

// Connect to database
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=healthcare_cms',
        'root',
        ''
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to database\n\n";
} catch (PDOException $e) {
    echo "❌ ERROR: Cannot connect to database\n";
    echo "   " . $e->getMessage() . "\n";
    exit(1);
}

// FIX #1: Create fallback user for media uploads
echo "FIX #1: Creating fallback user for media uploads...\n";
try {
    $stmt = $pdo->prepare("
        INSERT INTO users (id, username, email, password_hash, role, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE username = username
    ");
    
    $stmt->execute([
        '550e8400-e29b-41d4-a716-446655440001',
        'system',
        'system@healthcare-cms.local',
        password_hash('system_fallback_2025', PASSWORD_DEFAULT),
        'admin'
    ]);
    
    echo "✓ Fallback user created/verified (ID: 550e8400-e29b-41d4-a716-446655440001)\n";
    echo "  Username: system\n";
    echo "  Purpose: Media upload FK constraint\n\n";
} catch (PDOException $e) {
    echo "❌ ERROR creating fallback user: " . $e->getMessage() . "\n\n";
}

// FIX #2: Check if testuser exists and get their ID
echo "FIX #2: Checking testuser exists...\n";
try {
    $stmt = $pdo->query("SELECT id, username FROM users WHERE username = 'testuser' LIMIT 1");
    $testuser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($testuser) {
        echo "✓ testuser found\n";
        echo "  ID: " . $testuser['id'] . "\n";
        echo "  Username: " . $testuser['username'] . "\n\n";
        $testuserID = $testuser['id'];
    } else {
        echo "⚠ WARNING: testuser NOT found in database!\n";
        echo "  Media uploads will use fallback 'system' user\n\n";
        $testuserID = null;
    }
} catch (PDOException $e) {
    echo "❌ ERROR checking testuser: " . $e->getMessage() . "\n\n";
    $testuserID = null;
}

// FIX #3: Create collection page if it doesn't exist
echo "FIX #3: Creating collection page 'all-materials'...\n";
try {
    // Check if page already exists
    $stmt = $pdo->prepare("SELECT id FROM pages WHERE slug = ?");
    $stmt->execute(['all-materials']);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "⚠ Page 'all-materials' already exists (ID: " . $existing['id'] . ")\n";
        echo "  Skipping creation.\n\n";
    } else {
        // Create the page
        $pageId = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        $createdBy = $testuserID ?? '550e8400-e29b-41d4-a716-446655440001';
        
        $stmt = $pdo->prepare("
            INSERT INTO pages (
                id, title, slug, type, status, 
                seo_title, seo_description, 
                collection_config,
                created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $collectionConfig = json_encode([
            'section' => null,  // Show all materials (guides + articles)
            'sortBy' => 'created_at',
            'sortOrder' => 'DESC'
        ]);
        
        $stmt->execute([
            $pageId,
            'Все материалы',
            'all-materials',
            'collection',
            'published',
            'Все материалы — Healthcare Hacks Brazil',
            'Полная коллекция гайдов и статей о медицинской системе Бразилии',
            $collectionConfig,
            $createdBy
        ]);
        
        echo "✓ Collection page created!\n";
        echo "  ID: $pageId\n";
        echo "  Title: Все материалы\n";
        echo "  Slug: all-materials\n";
        echo "  Type: collection\n";
        echo "  Status: published\n";
        echo "  Config: $collectionConfig\n\n";
        
        echo "  🔗 URL: http://localhost/healthcare-cms-backend/page/all-materials\n\n";
    }
} catch (PDOException $e) {
    echo "❌ ERROR creating collection page: " . $e->getMessage() . "\n\n";
}

// VERIFICATION: Check current state
echo "========================================\n";
echo "VERIFICATION\n";
echo "========================================\n\n";

// Check users
echo "Users in database:\n";
$stmt = $pdo->query("SELECT username, role FROM users ORDER BY username");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $user) {
    echo "  - {$user['username']} ({$user['role']})\n";
}
echo "\n";

// Check collection pages
echo "Collection pages:\n";
$stmt = $pdo->query("SELECT title, slug, status FROM pages WHERE type = 'collection'");
$collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($collections) > 0) {
    foreach ($collections as $col) {
        echo "  - {$col['title']} (slug: {$col['slug']}, status: {$col['status']})\n";
    }
} else {
    echo "  (none)\n";
}
echo "\n";

// Check media files
echo "Media files count:\n";
$stmt = $pdo->query("SELECT COUNT(*) as total FROM media");
$mediaCount = $stmt->fetch(PDO::FETCH_ASSOC);
echo "  Total: " . $mediaCount['total'] . " files\n\n";

echo "========================================\n";
echo "✅ Quick fixes applied!\n";
echo "========================================\n\n";

echo "Next steps:\n";
echo "1. Test media upload: http://localhost/healthcare-cms-frontend (🖼️ Медиатека)\n";
echo "2. View collection: http://localhost/healthcare-cms-backend/page/all-materials\n";
echo "3. Check full report: DIAGNOSTIC_REPORT_Nov10_2025.md\n\n";
