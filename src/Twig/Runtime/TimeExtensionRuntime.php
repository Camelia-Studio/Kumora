<?php

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class TimeExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct()
    {
        // Inject dependencies if needed
    }

    public function timeDiff($value): string
    {
        $now = time();
        $diff = $now - $value;

        // Moins d'une minute
        if ($diff < 60) {
            return 'Il y a ' . $diff . ' seconde' . ($diff > 1 ? 's' : '');
        }

        // Moins d'une heure
        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return 'Il y a ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }

        // Moins d'un jour
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return 'Il y a ' . $hours . ' heure' . ($hours > 1 ? 's' : '');
        }

        // Plus d'un jour
        $days = floor($diff / 86400);
        return 'Il y a ' . $days . ' jour' . ($days > 1 ? 's' : '');
    }
}
