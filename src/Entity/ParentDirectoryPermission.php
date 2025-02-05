<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RoleEnum;
use App\Repository\ParentDirectoryPermissionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParentDirectoryPermissionRepository::class)]
class ParentDirectoryPermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: RoleEnum::class)]
    private ?RoleEnum $role = null;

    #[ORM\Column]
    private ?bool $read = null;

    #[ORM\Column]
    private ?bool $write = null;

    #[ORM\ManyToOne(inversedBy: 'parentDirectoryPermissions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ParentDirectory $parentDirectory = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRole(): ?RoleEnum
    {
        return $this->role;
    }

    public function setRole(RoleEnum $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isRead(): ?bool
    {
        return $this->read;
    }

    public function setRead(bool $read): static
    {
        $this->read = $read;

        return $this;
    }

    public function isWrite(): ?bool
    {
        return $this->write;
    }

    public function setWrite(bool $write): static
    {
        $this->write = $write;

        return $this;
    }

    public function getParentDirectory(): ?ParentDirectory
    {
        return $this->parentDirectory;
    }

    public function setParentDirectory(?ParentDirectory $parentDirectory): static
    {
        $this->parentDirectory = $parentDirectory;

        return $this;
    }
}
