<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class BasenameExtensionRuntime implements RuntimeExtensionInterface
{
    public function basename($value)
    {
        return \basename((string) $value);
    }
}
