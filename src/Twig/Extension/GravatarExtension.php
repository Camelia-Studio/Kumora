<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\GravatarExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class GravatarExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_gravatar', [GravatarExtensionRuntime::class, 'getGravatar']),
        ];
    }
}
