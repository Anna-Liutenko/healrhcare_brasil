<?php

declare(strict_types=1);

namespace Infrastructure\Auth;

use Infrastructure\Middleware\ApiLogger;
use Infrastructure\Repository\MySQLSessionRepository;
use Infrastructure\Repository\MySQLUserRepository;
use Domain\Entity\User;

class AuthHelper
{
    public static function requireAuth(): User
    {
        $user = self::getCurrentUser();

        if ($user === null) {
            throw new UnauthorizedException('Authentication required', 401);
        }

        return $user;
    }

    public static function getCurrentUser(): ?User
    {
        $token = self::extractBearerToken();

        if ($token === null) {
            return null;
        }

        $sessionRepo = new MySQLSessionRepository();

        if (!$sessionRepo->isValid($token)) {
            return null;
        }

        $session = $sessionRepo->findByToken($token);
        if (!$session) {
            return null;
        }

        $userRepo = new MySQLUserRepository();
        $user = $userRepo->findById($session['user_id']);

        return $user;
    }

    /**
     * Extract bearer token from request headers (public helper for controllers)
     */
    public static function extractBearerToken(): ?string
    {
        return self::extractBearerTokenInternal();
    }

    private static function extractBearerTokenInternal(): ?string
    {
        $headers = ApiLogger::getRequestHeaders();
        $authHeader = $headers['Authorization'] ?? ($headers['authorization'] ?? null);

        if (!$authHeader) {
            return null;
        }

        $matches = [];
        if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return null;
        }

        return $matches[1];
    }

    
}
