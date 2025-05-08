<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AccessGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AccessGroupRepository::class)]
class AccessGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column]
    private ?int $position = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'accessGroup')]
    private Collection $users;

    #[ORM\OneToMany(targetEntity: ParentDirectory::class, mappedBy: 'ownerRole')]
    private Collection $parentDirectories;

    #[ORM\OneToMany(targetEntity: ParentDirectoryPermission::class, mappedBy: 'role')]
    private Collection $parentDirectoryPermissions;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->parentDirectories = new ArrayCollection();
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

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setAccessGroup($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        // set the owning side to null (unless already changed)
        if ($this->users->removeElement($user) && $user->getAccessGroup() === $this) {
            $user->setAccessGroup(null);
        }

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getParentDirectories(): Collection
    {
        return $this->parentDirectories;
    }

    public function addParentDirectory(ParentDirectory $parentDirectory): static
    {
        if (!$this->parentDirectories->contains($parentDirectory)) {
            $this->parentDirectories->add($parentDirectory);
            $parentDirectory->setOwnerRole($this);
        }

        return $this;
    }

    public function removeParentDirectory(ParentDirectory $parentDirectory): static
    {
        // set the owning side to null (unless already changed)
        if ($this->parentDirectories->removeElement($parentDirectory) && $parentDirectory->getOwnerRole() === $this) {
            $parentDirectory->setOwnerRole(null);
        }

        return $this;
    }

    /**
     * @return Collection
     */
    /**
     * @return Collection
     */
    public function getParentDirectoryPermissions(): Collection
    {
        return $this->parentDirectoryPermissions;
    }

    public function addParentDirectoryPermission(ParentDirectoryPermission $parentDirectoryPermission): static
    {
        if (!$this->parentDirectoryPermissions->contains($parentDirectoryPermission)) {
            $this->parentDirectoryPermissions->add($parentDirectoryPermission);
            $parentDirectoryPermission->setRole($this);
        }

        return $this;
    }

    public function removeParentDirectoryPermission(ParentDirectoryPermission $parentDirectoryPermission): static
    {
        if ($this->parentDirectoryPermissions->removeElement($parentDirectoryPermission) && $parentDirectoryPermission->getRole() === $this) {
            $parentDirectoryPermission->setRole(null);
        }

        return $this;
    }
}
