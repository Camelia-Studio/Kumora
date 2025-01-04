<?php

namespace App\Twig\Extension;

use App\Twig\Runtime\BasenameExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class BasenameExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('basename', [BasenameExtensionRuntime::class, 'basename']),
        ];
    }
}
