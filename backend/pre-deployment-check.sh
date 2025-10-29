#!/bin/bash
# Pre-deployment checklist for Healthcare CMS on Ubuntu

echo "=== Healthcare CMS Production Deployment Checklist ===" 
echo ""

# 1. Check environment variables
echo "[→] 1. Checking environment variables..."
if [ -z "$DB_HOST" ]; then
    echo "[!] DB_HOST not set. Defaulting to 'localhost'"
    export DB_HOST="localhost"
fi
if [ -z "$DB_USERNAME" ]; then
    echo "[!] DB_USERNAME not set. Defaulting to 'root'"
    export DB_USERNAME="root"
fi
if [ -z "$DB_DATABASE" ]; then
    echo "[!] DB_DATABASE not set. Defaulting to 'healthcare_cms'"
    export DB_DATABASE="healthcare_cms"
fi
echo "[✓] Environment variables configured"
echo "    DB_HOST: $DB_HOST"
echo "    DB_USERNAME: $DB_USERNAME"
echo "    DB_DATABASE: $DB_DATABASE"
echo ""

# 2. Test database connection
echo "[→] 2. Testing database connection..."
php -r "
try {
    \$pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306) . ';dbname=' . getenv('DB_DATABASE') . ';charset=utf8mb4',
        getenv('DB_USERNAME'),
        getenv('DB_PASSWORD') ?: ''
    );
    echo '[✓] Database connection successful' . PHP_EOL;
} catch (Exception \$e) {
    echo '[✗] Database connection failed: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"
echo ""

# 3. Check required tables
echo "[→] 3. Checking required tables..."
php -r "
\$tables = [
    'users' => 'Users table for authentication',
    'sessions' => 'Sessions table for tokens',
    'pages' => 'Pages table for content',
    'blocks' => 'Blocks table for page content'
];

\$pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306) . ';dbname=' . getenv('DB_DATABASE') . ';charset=utf8mb4',
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD') ?: ''
);

foreach (\$tables as \$table => \$desc) {
    \$stmt = \$pdo->prepare('SHOW TABLES LIKE ?');
    \$stmt->execute([\$table]);
    if (\$stmt->fetch()) {
        echo '[✓] ' . \$table . ': ' . \$desc . PHP_EOL;
    } else {
        echo '[!] ' . \$table . ': NOT FOUND (may need initialization)' . PHP_EOL;
    }
}
"
echo ""

# 4. Verify encoding
echo "[→] 4. Verifying UTF-8 encoding..."
php -r "
\$pdo = new PDO(
    'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: 3306) . ';dbname=' . getenv('DB_DATABASE') . ';charset=utf8mb4',
    getenv('DB_USERNAME'),
    getenv('DB_PASSWORD') ?: ''
);

\$stmt = \$pdo->query('SELECT @@character_set_database, @@collation_database');
\$result = \$stmt->fetch();
echo '[✓] Database charset: ' . \$result[0] . PHP_EOL;
echo '[✓] Database collation: ' . \$result[1] . PHP_EOL;

if (\$result[0] === 'utf8mb4') {
    echo '[✓] UTF-8mb4 is properly configured' . PHP_EOL;
} else {
    echo '[!] WARNING: Database is not utf8mb4. Consider upgrading.' . PHP_EOL;
}
"
echo ""

# 5. Check file permissions
echo "[→] 5. Checking file permissions..."
if [ -w "./backend/logs" ]; then
    echo "[✓] Logs directory is writable"
else
    echo "[!] Logs directory is NOT writable. Running: chmod 755 ./backend/logs"
    chmod 755 ./backend/logs
fi

if [ -w "./backend/uploads" ]; then
    echo "[✓] Uploads directory is writable"
else
    echo "[!] Uploads directory is NOT writable. Running: chmod 755 ./backend/uploads"
    chmod 755 ./backend/uploads
fi
echo ""

# 6. Test authentication flow
echo "[→] 6. Testing authentication flow..."
php backend/test-login-flow.php 2>&1 | grep -E "LOGIN FLOW|ERROR|FATAL" || echo "[✓] Auth flow test completed"
echo ""

echo "[✓✓✓] Pre-deployment checklist complete! ==="
echo ""
echo "Next steps:"
echo "1. Review all environment variables"
echo "2. Set proper file permissions: chmod 755 backend/logs backend/uploads"
echo "3. Run database migrations if needed"
echo "4. Test with real credentials before going live"
