<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\SizeExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class SizeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('show_size', [SizeExtensionRuntime::class, 'showSize']),
        ];
    }
}
