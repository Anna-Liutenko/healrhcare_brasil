<?php

declare(strict_types=1);

namespace Application\UseCase;

use Domain\Repository\RateLimitRepositoryInterface;
use Domain\Entity\RateLimit;
use Exception;
use Ramsey\Uuid\Uuid;

/**
 * Check Rate Limit Use Case
 *
 * Проверка ограничения частоты запросов:
 * - 5 попыток на действие в течение 15 минут
 * - Блокировка на 15 минут при превышении
 */
class CheckRateLimit
{
    public function __construct(
        private RateLimitRepositoryInterface $rateLimitRepository
    ) {}

    /**
     * Проверить, разрешена ли операция
     * 
     * @throws Exception если превышено ограничение
     */
    public function checkAllowed(string $identifier): void
    {
        $rateLimit = $this->rateLimitRepository->findByIdentifier($identifier);

        if ($rateLimit === null) {
            // Первая попытка - разрешено
            return;
        }

        // Проверить, заблокировано ли
        if ($rateLimit->isLocked()) {
            $remaining = $rateLimit->getRemainingLockSeconds();
            $minutes = (int) ceil($remaining / 60);
            throw new Exception("Too many attempts. Try again in $minutes minutes.");
        }

        // Проверить, истёк ли временное окно неудачных попыток
        if ($rateLimit->isAttemptWindowExpired()) {
            // Окно истекло - разблокируем
            $rateLimit->unlock();
            $this->rateLimitRepository->update($rateLimit);
            return;
        }

        // Проверить, превышен ли лимит попыток
        if ($rateLimit->isLimitExceeded()) {
            $rateLimit->lock(15); // Блокировка на 15 минут
            $this->rateLimitRepository->update($rateLimit);
            throw new Exception('Too many attempts. Account locked for 15 minutes.');
        }
    }

    /**
     * Записать попытку
     */
    public function recordAttempt(string $identifier): void
    {
        $rateLimit = $this->rateLimitRepository->findByIdentifier($identifier);

        if ($rateLimit === null) {
            // Создаём новый rate limit
            $rateLimit = RateLimit::create(Uuid::uuid4()->toString(), $identifier);
        } else {
            // Увеличиваем счётчик
            $rateLimit->incrementAttempts();

            // Проверяем, нужно ли заблокировать после этой попытки
            if ($rateLimit->isLimitExceeded()) {
                $rateLimit->lock(15);
            }
        }

        $this->rateLimitRepository->save($rateLimit);
    }

    /**
     * Получить статус ограничения
     * 
     * @return array{
     *     identifier: string,
     *     attempts: int,
     *     isLocked: bool,
     *     remainingSeconds: int,
     *     allowsAttempt: bool
     * }
     */
    public function getStatus(string $identifier): array
    {
        $rateLimit = $this->rateLimitRepository->findByIdentifier($identifier);

        if ($rateLimit === null) {
            return [
                'identifier' => $identifier,
                'attempts' => 0,
                'isLocked' => false,
                'remainingSeconds' => 0,
                'allowsAttempt' => true,
            ];
        }

        return [
            'identifier' => $identifier,
            'attempts' => $rateLimit->getAttempts(),
            'isLocked' => $rateLimit->isLocked(),
            'remainingSeconds' => $rateLimit->getRemainingLockSeconds(),
            'allowsAttempt' => !$rateLimit->isLocked(),
        ];
    }

    /**
     * Очистить rate limit
     */
    public function reset(string $identifier): void
    {
        $rateLimit = $this->rateLimitRepository->findByIdentifier($identifier);
        if ($rateLimit !== null) {
            $rateLimit->unlock();
            $this->rateLimitRepository->update($rateLimit);
        }
    }
}
