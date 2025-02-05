<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\EnvironmentExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class EnvironmentExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_env', [EnvironmentExtensionRuntime::class, 'getEnv']),
        ];
    }
}
