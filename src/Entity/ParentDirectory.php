<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ParentDirectoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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

    #[ORM\ManyToOne(inversedBy: 'parentDirectories')]
    private ?AccessGroup $ownerRole = null;

    #[ORM\ManyToOne(inversedBy: 'parentDirectories')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $userCreated = null;

    #[ORM\Column]
    private ?bool $isPublic = null;

    /**
     * @var Collection<int, ParentDirectoryPermission>
     */
    #[ORM\OneToMany(targetEntity: ParentDirectoryPermission::class, mappedBy: 'parentDirectory', orphanRemoval: true, cascade: ['persist'])]
    private Collection $parentDirectoryPermissions;

    public function __construct()
    {
        $this->parentDirectoryPermissions = new ArrayCollection();
    }

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

    public function getOwnerRole(): ?AccessGroup
    {
        return $this->ownerRole;
    }

    public function setOwnerRole(?AccessGroup $ownerRole): static
    {
        $this->ownerRole = $ownerRole;

        return $this;
    }

    public function getUserCreated(): ?User
    {
        return $this->userCreated;
    }

    public function setUserCreated(?User $userCreated): static
    {
        $this->userCreated = $userCreated;

        return $this;
    }

    public function isPublic(): ?bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    /**
     * @return Collection<int, ParentDirectoryPermission>
     */
    public function getParentDirectoryPermissions(): Collection
    {
        return $this->parentDirectoryPermissions;
    }

    public function addParentDirectoryPermission(ParentDirectoryPermission $parentDirectoryPermission): static
    {
        if (!$this->parentDirectoryPermissions->contains($parentDirectoryPermission)) {
            $this->parentDirectoryPermissions->add($parentDirectoryPermission);
            $parentDirectoryPermission->setParentDirectory($this);
        }

        return $this;
    }

    public function removeParentDirectoryPermission(ParentDirectoryPermission $parentDirectoryPermission): static
    {
        // set the owning side to null (unless already changed)
        if ($this->parentDirectoryPermissions->removeElement($parentDirectoryPermission) && $parentDirectoryPermission->getParentDirectory() === $this) {
            $parentDirectoryPermission->setParentDirectory(null);
        }

        return $this;
    }
}
