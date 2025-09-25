<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\TimeExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TimeExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // If your filter generates SAFE HTML, you should add a third
            // parameter: ['is_safe' => ['html']]
            // Reference: https://twig.symfony.com/doc/3.x/advanced.html#automatic-escaping
            new TwigFilter('time_diff', [TimeExtensionRuntime::class, 'timeDiff']),
            new TwigFilter('last_login_format', [TimeExtensionRuntime::class, 'lastLoginFormat']),
        ];
    }
}
