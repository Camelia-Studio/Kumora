<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\UserAction;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class UserActionExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('action_icon', $this->getActionIcon(...)),
            new TwigFilter('action_color', $this->getActionColor(...)),
            new TwigFilter('time_diff', $this->getTimeDiff(...)),
        ];
    }

    public function getActionIcon(string $actionType): string
    {
        return match ($actionType) {
            UserAction::ACTION_FOLDER_CREATE => 'fa6-solid:folder-plus',
            UserAction::ACTION_FILE_UPLOAD => 'fa6-solid:cloud-arrow-up',
            UserAction::ACTION_FILE_MOVE, UserAction::ACTION_FOLDER_MOVE => 'mdi:file-move',
            UserAction::ACTION_FILE_RENAME, UserAction::ACTION_FOLDER_RENAME => 'fa6-solid:pencil',
            UserAction::ACTION_FILE_DELETE, UserAction::ACTION_FOLDER_DELETE => 'fa6-solid:trash-can',
            UserAction::ACTION_PERMISSION_CHANGE => 'fa6-solid:shield',
            default => 'fa6-solid:file',
        };
    }

    public function getActionColor(string $actionType): string
    {
        return match ($actionType) {
            UserAction::ACTION_FOLDER_CREATE, UserAction::ACTION_FILE_UPLOAD => 'text-green-600 dark:text-green-400',
            UserAction::ACTION_FILE_MOVE, UserAction::ACTION_FOLDER_MOVE => 'text-blue-600 dark:text-blue-400',
            UserAction::ACTION_FILE_RENAME, UserAction::ACTION_FOLDER_RENAME => 'text-amber-600 dark:text-amber-400',
            UserAction::ACTION_FILE_DELETE, UserAction::ACTION_FOLDER_DELETE => 'text-red-600 dark:text-red-400',
            UserAction::ACTION_PERMISSION_CHANGE => 'text-purple-600 dark:text-purple-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
    }

    public function getTimeDiff(\DateTimeInterface|int $date): string
    {
        $now = new \DateTimeImmutable();

        // Si c'est un timestamp (int), le convertir en DateTimeImmutable
        if (is_int($date)) {
            $date = new \DateTimeImmutable('@' . $date);
        }

        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return 1 === $diff->y ? 'il y a 1 an' : "il y a {$diff->y} ans";
        }

        if ($diff->m > 0) {
            return 1 === $diff->m ? 'il y a 1 mois' : "il y a {$diff->m} mois";
        }

        if ($diff->d > 0) {
            return 1 === $diff->d ? 'il y a 1 jour' : "il y a {$diff->d} jours";
        }

        if ($diff->h > 0) {
            return 1 === $diff->h ? 'il y a 1 heure' : "il y a {$diff->h} heures";
        }

        if ($diff->i > 0) {
            return 1 === $diff->i ? 'il y a 1 minute' : "il y a {$diff->i} minutes";
        }

        return 'il y a quelques secondes';
    }
}
