<?php

declare(strict_types=1);

namespace Infrastructure\Auth;

class UnauthorizedException extends \Exception
{
    private int $httpCode;

    public function __construct(string $message, int $httpCode = 401)
    {
        parent::__construct($message);
        $this->httpCode = $httpCode;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
