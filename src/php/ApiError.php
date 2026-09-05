<?php
declare(strict_types=1);

namespace CaddyPanel;

final class ApiError extends \RuntimeException
{
    public readonly string $errorCode;
    public readonly array $params;

    public function __construct(string $errorCode, int $status = 400, array $params = [])
    {
        parent::__construct($errorCode, $status);
        $this->errorCode = $errorCode;
        $this->params = $params;
    }
}
