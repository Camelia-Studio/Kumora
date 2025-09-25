<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class TimeExtensionRuntime implements RuntimeExtensionInterface
{
    private const SECONDS_IN_MINUTE = 60;
    private const SECONDS_IN_HOUR = 3600;
    private const SECONDS_IN_DAY = 86400;
    private const SECONDS_IN_WEEK = 604800;

    public function __construct()
    {
        // Inject dependencies if needed
    }

    public function timeDiff($value): string
    {
        $now = time();
        $diff = $now - $value;

        // Moins d'une minute
        if ($diff < self::SECONDS_IN_MINUTE) {
            return 'Il y a ' . $diff . ' seconde' . ($diff > 1 ? 's' : '');
        }

        // Moins d'une heure
        if ($diff < self::SECONDS_IN_HOUR) {
            $minutes = floor($diff / self::SECONDS_IN_MINUTE);
            return 'Il y a ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }

        // Moins d'un jour
        if ($diff < self::SECONDS_IN_DAY) {
            $hours = floor($diff / self::SECONDS_IN_HOUR);
            return 'Il y a ' . $hours . ' heure' . ($hours > 1 ? 's' : '');
        }

        // Plus d'un jour
        $days = floor($diff / self::SECONDS_IN_DAY);
        return 'Il y a ' . $days . ' jour' . ($days > 1 ? 's' : '');
    }

    public function lastLoginFormat(?\DateTimeImmutable $lastLogin): string
    {
        if (null === $lastLogin) {
            return 'Jamais connecté';
        }

        $now = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $lastLogin->getTimestamp();

        // Moins d'une minute
        if ($diff < self::SECONDS_IN_MINUTE) {
            return 'Il y a ' . $diff . ' seconde' . ($diff > 1 ? 's' : '');
        }

        // Moins d'une heure
        if ($diff < self::SECONDS_IN_HOUR) {
            $minutes = floor($diff / self::SECONDS_IN_MINUTE);
            return 'Il y a ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '');
        }

        // Moins d'un jour
        if ($diff < self::SECONDS_IN_DAY) {
            $hours = floor($diff / self::SECONDS_IN_HOUR);
            return 'Il y a ' . $hours . ' heure' . ($hours > 1 ? 's' : '');
        }

        // Moins de 7 jours
        if ($diff < self::SECONDS_IN_WEEK) {
            $days = floor($diff / self::SECONDS_IN_DAY);
            return 'Il y a ' . $days . ' jour' . ($days > 1 ? 's' : '');
        }

        // Plus de 7 jours - afficher la date complète
        return 'Le ' . $lastLogin->format('d/m/Y à H:i');
    }
}
