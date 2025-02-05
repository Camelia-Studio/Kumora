<?php

declare(strict_types=1);

namespace App\Twig\Runtime;

use App\Entity\ParentDirectory;
use App\Repository\ParentDirectoryRepository;
use Twig\Extension\RuntimeExtensionInterface;

class EntityExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ParentDirectoryRepository $parentDirectoryRepository
    ) {
    }

    public function getParentDir(string $value): ParentDirectory
    {
        return $this->parentDirectoryRepository->findOneBy(['name' => $value]);
    }
}
