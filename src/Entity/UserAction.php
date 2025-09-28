<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserActionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserActionRepository::class)]
#[ORM\Table(name: 'user_actions')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_user_created')]
#[ORM\Index(columns: ['action_type'], name: 'idx_action_type')]
class UserAction
{
    public const ACTION_FOLDER_CREATE = 'folder_create';
    public const ACTION_FILE_UPLOAD = 'file_upload';
    public const ACTION_FILE_MOVE = 'file_move';
    public const ACTION_FILE_RENAME = 'file_rename';
    public const ACTION_FILE_DELETE = 'file_delete';
    public const ACTION_FOLDER_DELETE = 'folder_delete';
    public const ACTION_FOLDER_RENAME = 'folder_rename';
    public const ACTION_FOLDER_MOVE = 'folder_move';
    public const ACTION_PERMISSION_CHANGE = 'permission_change';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $actionType = null;

    #[ORM\Column(length: 500)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetPath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $oldValue = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $newValue = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getActionType(): ?string
    {
        return $this->actionType;
    }

    public function setActionType(string $actionType): static
    {
        $this->actionType = $actionType;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getTargetPath(): ?string
    {
        return $this->targetPath;
    }

    public function setTargetPath(?string $targetPath): static
    {
        $this->targetPath = $targetPath;

        return $this;
    }

    public function getOldValue(): ?string
    {
        return $this->oldValue;
    }

    public function setOldValue(?string $oldValue): static
    {
        $this->oldValue = $oldValue;

        return $this;
    }

    public function getNewValue(): ?string
    {
        return $this->newValue;
    }

    public function setNewValue(?string $newValue): static
    {
        $this->newValue = $newValue;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getActionIcon(): string
    {
        return match ($this->actionType) {
            self::ACTION_FOLDER_CREATE => 'fa6-solid:folder-plus',
            self::ACTION_FILE_UPLOAD => 'fa6-solid:cloud-arrow-up',
            self::ACTION_FILE_MOVE, self::ACTION_FOLDER_MOVE => 'mdi:file-move',
            self::ACTION_FILE_RENAME, self::ACTION_FOLDER_RENAME => 'fa6-solid:pencil',
            self::ACTION_FILE_DELETE, self::ACTION_FOLDER_DELETE => 'fa6-solid:trash-can',
            self::ACTION_PERMISSION_CHANGE => 'fa6-solid:shield',
            default => 'fa6-solid:file',
        };
    }

    public function getActionColor(): string
    {
        return match ($this->actionType) {
            self::ACTION_FOLDER_CREATE, self::ACTION_FILE_UPLOAD => 'text-green-600 dark:text-green-400',
            self::ACTION_FILE_MOVE, self::ACTION_FOLDER_MOVE => 'text-blue-600 dark:text-blue-400',
            self::ACTION_FILE_RENAME, self::ACTION_FOLDER_RENAME => 'text-amber-600 dark:text-amber-400',
            self::ACTION_FILE_DELETE, self::ACTION_FOLDER_DELETE => 'text-red-600 dark:text-red-400',
            self::ACTION_PERMISSION_CHANGE => 'text-purple-600 dark:text-purple-400',
            default => 'text-gray-600 dark:text-gray-400',
        };
    }
}