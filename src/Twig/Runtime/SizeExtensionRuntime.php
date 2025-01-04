<?php

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class SizeExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct()
    {
        // Inject dependencies if needed
    }

    public function showSize($value)
    {
        $bytes = $value;
        $size = ['B', 'KB', 'MB', 'GB','TB'];
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf('%.1f', $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
    }
}
