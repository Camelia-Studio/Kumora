<?php

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class BasenameExtensionRuntime implements RuntimeExtensionInterface
{

    public function basename($value)
    {
        return \basename($value);
    }
}
