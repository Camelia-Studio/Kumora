<?php

namespace App\Entity;

use App\Enum\RoleEnum;
use App\Repository\ParentDirectoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParentDirectoryRepository::class)]
class ParentDirectory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(enumType: RoleEnum::class)]
    private ?RoleEnum $ownerRole = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getOwnerRole(): ?RoleEnum
    {
        return $this->ownerRole;
    }

    public function setOwnerRole(RoleEnum $ownerRole): static
    {
        $this->ownerRole = $ownerRole;

        return $this;
    }
}
