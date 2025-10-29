<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require 'vendor/autoload.php';

echo "=== AUTH LOGIN FLOW DIAGNOSTIC TEST ===\n\n";

// Simulate the exact login request
$testUsername = 'anna';
$testPassword = 'TestPass123!';

echo "[→] Testing login flow for user: '$testUsername' with password: '$testPassword'\n\n";

try {
    // Step 1: Initialize repositories (like AuthController does)
    echo "[Step 1] Initializing repositories...\n";
    $userRepository = new \Infrastructure\Repository\MySQLUserRepository();
    $sessionRepository = new \Infrastructure\Repository\MySQLSessionRepository();
    echo "[✓] Repositories initialized\n\n";
    
    // Step 2: Find user by username
    echo "[Step 2] Finding user by username...\n";
    $user = $userRepository->findByUsername($testUsername);
    
    if (!$user) {
        echo "[✗] User not found in database\n";
        exit(1);
    }
    echo "[✓] User found: {$user->getUsername()} (id: {$user->getId()})\n\n";
    
    // Step 3: Verify password
    echo "[Step 3] Verifying password...\n";
    $passwordValid = $user->verifyPassword($testPassword);
    echo "[" . ($passwordValid ? "✓" : "✗") . "] Password verification result: " . ($passwordValid ? "VALID" : "INVALID") . "\n";
    
    if (!$passwordValid) {
        echo "    Password hash in DB: " . substr($user->getPasswordHash(), 0, 20) . "...\n";
        exit(1);
    }
    echo "\n";
    
    // Step 4: Check user is active
    echo "[Step 4] Checking if user is active...\n";
    $isActive = $user->isActive();
    echo "[" . ($isActive ? "✓" : "✗") . "] User is active: " . ($isActive ? "YES" : "NO") . "\n";
    
    if (!$isActive) {
        echo "[✗] User is inactive\n";
        exit(1);
    }
    echo "\n";
    
    // Step 5: Update last login
    echo "[Step 5] Updating last login timestamp...\n";
    $user->updateLastLogin();
    $userRepository->save($user);
    echo "[✓] Last login updated\n\n";
    
    // Step 6: Create session
    echo "[Step 6] Creating session token...\n";
    $token = $sessionRepository->create($user->getId(), 86400);
    echo "[✓] Session token created: " . substr($token, 0, 20) . "...\n";
    echo "    Full token length: " . strlen($token) . " characters\n\n";
    
    // Step 7: Verify session can be retrieved
    echo "[Step 7] Verifying session is stored and retrievable...\n";
    $session = $sessionRepository->findByToken($token);
    if (!$session) {
        echo "[✗] Session not found after creation\n";
        exit(1);
    }
    echo "[✓] Session retrieved successfully\n";
    echo "    Session user_id: {$session['user_id']}\n";
    echo "    Session expires_at: {$session['expires_at']}\n\n";
    
    // Step 8: Verify session is valid
    echo "[Step 8] Checking if session is valid...\n";
    $isValid = $sessionRepository->isValid($token);
    echo "[" . ($isValid ? "✓" : "✗") . "] Session is valid: " . ($isValid ? "YES" : "NO") . "\n\n";
    
    echo "[✓✓✓] LOGIN FLOW SUCCESSFUL ✓✓✓\n";
    echo "Token for manual testing: $token\n";
    
} catch (Throwable $e) {
    echo "\n[✗✗✗] ERROR OCCURRED ✗✗✗\n";
    echo "Exception: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
