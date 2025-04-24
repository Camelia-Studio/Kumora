<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\RolesExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class RolesExtensionExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getHigherRoles', [RolesExtensionRuntime::class, 'getHigherRoles']),
            new TwigFunction('getHighestRole', [RolesExtensionRuntime::class, 'getHighestRole']),
        ];
    }
}
