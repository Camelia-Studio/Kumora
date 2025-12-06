<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\UserAction;
use App\Repository\ParentDirectoryRepository;
use App\Repository\UserActionRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UserActionLogger
{
    public function __construct(
        private readonly UserActionRepository $userActionRepository,
        private readonly Security $security,
        private readonly ParentDirectoryRepository $parentDirectoryRepository,
    ) {
    }

    public function logAction(
        string $actionType,
        string $description,
        ?string $targetPath = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?array $metadata = null,
        ?User $user = null,
    ): void {
        $user ??= $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Enrichir les métadonnées avec l'info public/privé du dossier
        $metadata ??= [];
        if (null !== $targetPath && '' !== $targetPath) {
            $pathParts = explode('/', $targetPath);
            $rootFolderName = $pathParts[0];

            $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $rootFolderName]);

            // Stocker si c'est un dossier public ou non
            // Si pas de ParentDirectory, considérer comme public (dossier normal, pas de partage)
            $metadata['is_public'] = null === $parentDir || $parentDir->isPublic();
        } else {
            // Actions sans targetPath (ex: changements de permissions) sont considérées publiques
            $metadata['is_public'] = true;
        }

        $userAction = new UserAction();
        $userAction
            ->setUser($user)
            ->setActionType($actionType)
            ->setDescription($description)
            ->setTargetPath($targetPath)
            ->setOldValue($oldValue)
            ->setNewValue($newValue)
            ->setMetadata($metadata);

        $this->userActionRepository->save($userAction, true);
    }

    public function logFolderCreate(
        string $folderPath,
        ?array $metadata = null,
    ): void {
        $isPrivate = $this->isPrivateAction($folderPath, $metadata);
        $description = $isPrivate
            ? $this->getPrivateDescription(UserAction::ACTION_FOLDER_CREATE)
            : "Création du dossier '{$folderPath}'";

        $this->logAction(
            UserAction::ACTION_FOLDER_CREATE,
            $description,
            $folderPath,
            null,
            null,
            $metadata,
        );
    }

    public function logFileUpload(
        string $filePath,
        ?UploadedFile $uploadedFile = null,
    ): void {
        $metadata = [];
        $isPrivate = $this->isPrivateAction($filePath);

        if (null !== $uploadedFile && !$isPrivate) {
            // Informations sûres de l'UploadedFile
            $metadata["original_name"] = $uploadedFile->getClientOriginalName();
            $metadata["mime_type"] = $uploadedFile->getClientMimeType();

            // Taille (éviter getSize() qui peut échouer sur fichiers temporaires)
            $fileSize = $this->getFileSizeFromUpload($uploadedFile);
            if (null !== $fileSize) {
                $metadata["file_size"] = $fileSize;
                $metadata["file_size_formatted"] = $this->formatFileSize(
                    $fileSize,
                );
            }

            // Extension
            if (
                "" !== $uploadedFile->getClientOriginalExtension() &&
                "0" !== $uploadedFile->getClientOriginalExtension()
            ) {
                $metadata[
                    "extension"
                ] = $uploadedFile->getClientOriginalExtension();
            }

            // Erreur d'upload s'il y en a une
            if (UPLOAD_ERR_OK !== $uploadedFile->getError()) {
                $metadata["upload_error"] = $this->getUploadErrorMessage(
                    $uploadedFile->getError(),
                );
            }
        }

        $description = $isPrivate
            ? $this->getPrivateDescription(UserAction::ACTION_FILE_UPLOAD)
            : "Upload du fichier '{$filePath}'";

        $this->logAction(
            UserAction::ACTION_FILE_UPLOAD,
            $description,
            $filePath,
            null,
            null,
            0 === count($metadata) ? $metadata : null,
        );
    }

    private function getFileSizeFromUpload(UploadedFile $uploadedFile): ?int
    {
        // Créer un dossier temporaire si nécessaire
        $tempDir = sys_get_temp_dir() . "/kumora_uploads";
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0o755, true);
        }

        // Générer un nom de fichier temporaire unique
        $tempFile =
            $tempDir .
            "/" .
            uniqid("upload_", true) .
            "." .
            $uploadedFile->getClientOriginalExtension();

        try {
            // Copier le fichier uploadé vers notre dossier temporaire (sans le déplacer)
            if (copy($uploadedFile->getPathname(), $tempFile)) {
                // Récupérer la taille du fichier copié
                $fileSize = filesize($tempFile);

                // Nettoyer le fichier temporaire immédiatement
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }

                return false !== $fileSize ? $fileSize : null;
            }
        } catch (\Exception) {
            // Nettoyer en cas d'erreur
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        // Fallback : essayer les méthodes standard
        try {
            return $uploadedFile->getSize();
        } catch (\Exception) {
            return null;
        }
    }

    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE
                => "Le fichier dépasse la taille maximale autorisée par PHP",
            UPLOAD_ERR_FORM_SIZE
                => "Le fichier dépasse la taille maximale autorisée par le formulaire",
            UPLOAD_ERR_PARTIAL
                => 'Le fichier n\'a été téléchargé que partiellement',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé',
            UPLOAD_ERR_NO_TMP_DIR => "Dossier temporaire manquant",
            UPLOAD_ERR_CANT_WRITE
                => 'Échec de l\'écriture du fichier sur le disque',
            UPLOAD_ERR_EXTENSION
                => 'Une extension PHP a arrêté l\'upload du fichier',
            default => 'Erreur d\'upload inconnue: ' . $errorCode,
        };
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ["B", "KB", "MB", "GB"];
        $bytes = max($bytes, 0);
        $pow = floor((0 !== $bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= 1 << 10 * $pow;

        return round($bytes, 2) . " " . $units[$pow];
    }

    public function logFileMove(string $oldPath, string $newPath): void
    {
        $isPrivate = $this->isPrivateAction($oldPath) || $this->isPrivateAction($newPath);
        $description = $isPrivate
            ? $this->getPrivateDescription(UserAction::ACTION_FILE_MOVE)
            : "Déplacement du fichier de '{$oldPath}' vers '{$newPath}'";

        $this->logAction(
            UserAction::ACTION_FILE_MOVE,
            $description,
            $newPath,
            $oldPath,
            $newPath,
        );
    }

    public function logFolderMove(string $oldPath, string $newPath): void
    {
        $isPrivate = $this->isPrivateAction($oldPath) || $this->isPrivateAction($newPath);
        $description = $isPrivate
            ? $this->getPrivateDescription(UserAction::ACTION_FOLDER_MOVE)
            : "Déplacement du dossier de '{$oldPath}' vers '{$newPath}'";

        $this->logAction(
            UserAction::ACTION_FOLDER_MOVE,
            $description,
            $newPath,
            $oldPath,
            $newPath,
        );
    }

    public function logFileRename(
        string $oldName,
        string $newName,
        string $path,
    ): void {
        $isPrivate = $this->isPrivateAction($path);
        $description = $isPrivate
            ? $this->getPrivateDescription(UserAction::ACTION_FILE_RENAME)
            : "Renommage du fichier '{$oldName}' en '{$newName}'";

        $this->logAction(
            UserAction::ACTION_FILE_RENAME,
            $description,
            $path,
            $oldName,
            $newName,
        );
    }

    public function logFolderRename(
        string $oldName,
        string $newName,
        string $path,
    ): void {
        $isPrivate = $this->isPrivateAction($path);
        $description = $isPrivate
            ? $this->getPrivateDescription(UserAction::ACTION_FOLDER_RENAME)
            : "Renommage du dossier '{$oldName}' en '{$newName}'";

        $this->logAction(
            UserAction::ACTION_FOLDER_RENAME,
            $description,
            $path,
            $oldName,
            $newName,
        );
    }

    public function logFileDelete(string $filePath): void
    {
        $isPrivate = $this->isPrivateAction($filePath);
        $description = $isPrivate
            ? $this->getPrivateDescription(UserAction::ACTION_FILE_DELETE)
            : "Suppression du fichier '{$filePath}'";

        $this->logAction(
            UserAction::ACTION_FILE_DELETE,
            $description,
            $filePath,
        );
    }

    public function logFolderDelete(string $folderPath): void
    {
        $isPrivate = $this->isPrivateAction($folderPath);
        $description = $isPrivate
            ? $this->getPrivateDescription(UserAction::ACTION_FOLDER_DELETE)
            : "Suppression du dossier '{$folderPath}'";

        $this->logAction(
            UserAction::ACTION_FOLDER_DELETE,
            $description,
            $folderPath,
        );
    }

    public function logPermissionChange(
        string $folderPath,
        string $oldPermissions,
        string $newPermissions,
    ): void {
        $isPrivate = $this->isPrivateAction($folderPath);
        $description = $isPrivate
            ? $this->getPrivateDescription(UserAction::ACTION_PERMISSION_CHANGE)
            : "Modification des permissions du dossier '{$folderPath}'";

        $this->logAction(
            UserAction::ACTION_PERMISSION_CHANGE,
            $description,
            $folderPath,
            $oldPermissions,
            $newPermissions,
        );
    }

    private function isPrivateAction(string $path, ?array $metadata = null): bool
    {
        if (null !== $metadata && isset($metadata['is_public'])) {
            return !$metadata['is_public'];
        }

        if ('' === $path) {
            return true;
        }

        $pathParts = explode('/', $path);
        $rootFolderName = $pathParts[0];

        $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $rootFolderName]);

        if (null === $parentDir) {
            return true;
        }

        return !$parentDir->isPublic();
    }

    private function getPrivateDescription(string $actionType): string
    {
        return match ($actionType) {
            UserAction::ACTION_FOLDER_CREATE => "Création d'un dossier racine privé",
            UserAction::ACTION_FILE_UPLOAD => "Upload d'un fichier",
            UserAction::ACTION_FILE_MOVE => "Déplacement d'un fichier",
            UserAction::ACTION_FOLDER_MOVE => "Déplacement d'un dossier",
            UserAction::ACTION_FILE_RENAME => "Renommage d'un fichier",
            UserAction::ACTION_FOLDER_RENAME => "Renommage d'un dossier",
            UserAction::ACTION_FILE_DELETE => "Suppression d'un fichier",
            UserAction::ACTION_FOLDER_DELETE => "Suppression d'un dossier",
            UserAction::ACTION_PERMISSION_CHANGE => "Modification de permissions",
            default => "Action sur un élément privé",
        };
    }
}
