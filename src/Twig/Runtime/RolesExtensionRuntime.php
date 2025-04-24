<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\AccessGroup;
use App\Repository\AccessGroupRepository;
use Twig\Extension\RuntimeExtensionInterface;

class RolesExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly AccessGroupRepository $accessGroupRepository,
    ) {
    }

    public function getHigherRoles(?AccessGroup $value): array
    {
        return $this->accessGroupRepository->getHigherRoles($value);
    }

    public function getHighestRole(): AccessGroup
    {
        return $this->accessGroupRepository->getHighestRole();
    }


    public function getLowestRole(): AccessGroup
    {
        return $this->accessGroupRepository->getLowestRole();
    }
}
