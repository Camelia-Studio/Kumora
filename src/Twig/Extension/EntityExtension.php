<?php

declare(strict_types=1);

namespace App\Twig\Extension;

use App\Twig\Runtime\EntityExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class EntityExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_parent_dir', [EntityExtensionRuntime::class, 'getParentDir']),
        ];
    }
}
