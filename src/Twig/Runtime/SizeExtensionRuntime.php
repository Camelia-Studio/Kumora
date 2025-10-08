<?php

declare(strict_types=1);

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
        // Si la taille n'a pas été calculée (performance), afficher "-"
        if ($value < 0) {
            return '-';
        }

        $bytes = $value;
        $size = ['B', 'KB', 'MB', 'GB','TB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.1f', $bytes / 1024 ** $factor) . ' ' . @$size[$factor];
    }
}
