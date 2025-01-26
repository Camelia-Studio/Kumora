<?php

declare(strict_types=1);

namespace App\Enum;

enum RoleEnum: string
{
    case CONSEIL_ADMINISTRATION = 'Conseil d\'administration';
    case ADMINISTRATEUR = 'Administrateur';
    case MEMBRE = 'Membre';
    case MEMBRE_HONORAIRE = 'Membre honoraire';
    case PARTENAIRE = 'Partenaire';
    case VISITEUR = 'Visiteur';

    public function getHigherRoles(): array
    {
        $roles = [];

        $isFound = false;
        foreach (RoleEnum::cases() as $role) {
            if ($role === $this) {
                $isFound = true;
            }

            if ($isFound) {
                break;
            }

            $roles[] = $role;
        }

        return $roles;
    }

    public function getInferiorRoles(): array
    {
        $roles = [];

        $isFound = false;
        foreach (RoleEnum::cases() as $role) {
            if ($role === $this) {
                $isFound = true;
            }

            if ($isFound) {
                $roles[] = $role;
            }
        }

        return $roles;
    }
}
