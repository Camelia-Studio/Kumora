<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\IconsExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class IconsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getIcons', [IconsExtensionRuntime::class, 'getIcons']),
            new TwigFunction('getFileType', [IconsExtensionRuntime::class, 'getFileType']),
        ];
    }
}
