<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class EnvironmentExtensionRuntime implements RuntimeExtensionInterface
{
    public function getEnv(string $value, string $default = ''): string
    {
        return $_ENV[$value] ?? $default;
    }
}
