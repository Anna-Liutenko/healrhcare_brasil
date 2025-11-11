<?php

declare(strict_types=1);

namespace Infrastructure\Middleware;

use Application\UseCase\CheckRateLimit;
use Domain\Repository\RateLimitRepositoryInterface;
use Exception;

/**
 * Rate Limit Middleware
 *
 * Проверяет ограничения частоты запросов для предотвращения brute-force атак
 * - Ограничение: 5 попыток на действие в течение 15 минут
 * - Блокировка на 15 минут при превышении лимита
 */
class RateLimitMiddleware
{
    private CheckRateLimit $checkRateLimit;

    public function __construct(RateLimitRepositoryInterface $rateLimitRepository)
    {
        $this->checkRateLimit = new CheckRateLimit($rateLimitRepository);
    }

    /**
     * Обработать middleware
     *
     * @param string $action Идентификатор действия (напр. 'login', 'register')
     * @param ?string $identifier IP адрес или user ID (если null - берём IP)
     * @throws Exception если превышено ограничение
     */
    public function handle(string $action, ?string $identifier = null): void
    {
        if ($identifier === null) {
            $identifier = $this->getClientIp();
        }

        $rateKey = "{$identifier}:{$action}";

        try {
            // Проверяем, разрешена ли операция
            $this->checkRateLimit->checkAllowed($rateKey);
        } catch (Exception $e) {
            // Выбрасываем исключение - будет обработано контроллером
            throw $e;
        }
    }

    /**
     * Записать попытку
     */
    public function recordAttempt(string $action, ?string $identifier = null): void
    {
        if ($identifier === null) {
            $identifier = $this->getClientIp();
        }

        $rateKey = "{$identifier}:{$action}";
        $this->checkRateLimit->recordAttempt($rateKey);
    }

    /**
     * Очистить ограничение (при успешной операции)
     */
    public function reset(string $action, ?string $identifier = null): void
    {
        if ($identifier === null) {
            $identifier = $this->getClientIp();
        }

        $rateKey = "{$identifier}:{$action}";
        $this->checkRateLimit->reset($rateKey);
    }

    /**
     * Получить IP адрес клиента
     */
    private function getClientIp(): string
    {
        // Проверяем различные способы получения IP
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            // Cloudflare
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Proxy
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return '0.0.0.0';
    }

    /**
     * Получить User-Agent
     */
    public static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
}
