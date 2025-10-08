<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\ParentDirectory;
use App\Repository\ParentDirectoryRepository;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class FileTable
{
    use DefaultActionTrait;
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Filesystem $defaultAdapter,
        private readonly ParentDirectoryRepository $parentDirectoryRepository,
        private readonly Security $security,
    ) {
    }

    #[LiveProp(writable: true, onUpdated: 'getFiles', url: true)]
    public string $path = '';

    #[LiveProp(writable: true, onUpdated: 'getFiles', url: true)]
    public string $sortBy = 'name';

    #[LiveProp(writable: true, onUpdated: 'getFiles', url: true)]
    public string $sortDirection = 'asc';

    #[LiveProp(writable: true, onUpdated: 'getFiles', url: true)]
    public string $search = '';

    /**
     * @var array<string>
     */
    #[LiveProp(writable: true)]
    public array $selectedFiles = [];

    public ?ParentDirectory $parentDir = null;

    /**
     * @throws FilesystemException
     */
    public function getFiles(): array
    {
        $this->path = $this->normalizePath($this->path);

        if ('' !== $this->path) {
            $pathExploded = explode('/', $this->path);

            $this->parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $pathExploded[0]]);

            if (!$this->parentDir instanceof \App\Entity\ParentDirectory || !$this->defaultAdapter->directoryExists($this->path)) {
                $this->path = '';
                return [];
            }

            if (!$this->security->isGranted('file_read', $this->parentDir)) {
                $this->path = '';
                return [];
            }
        }

        $files = $this->defaultAdapter->listContents('/' . $this->path);

        $realFiles = [];

        foreach ($files as $file) {
            $filename = basename((string)$file['path']);
            if (!str_starts_with($filename, '.')) {
                // On vérifie si l'utilisateur a le droit d'accéder au fichier (vérifier que owner_role du parentDirectory correspondant est bien le folderRole de l'utilisateur)
                $pathFile = explode('/', (string)$file['path']);
                if ('' !== $this->path) {
                    $parentDirectory = $this->parentDirectoryRepository->findOneBy(['name' => $pathFile[0]]);

                    if (null === $parentDirectory || !$this->security->isGranted('file_read', $parentDirectory)) {
                        continue;
                    }
                } elseif ('file' !== $file['type']) {
                    $parentDirectory = $this->parentDirectoryRepository->findOneBy(['name' => $filename]);

                    if (null === $parentDirectory || !$this->security->isGranted('file_read', $parentDirectory)) {
                        continue;
                    }
                }

                $realFiles[] = [
                    'type' => $file['type'],
                    'path' => $file['path'],
                    'last_modified' => $file['lastModified'],
                    'size' => $file['fileSize'] ?? $this->calculateSize($file),
                    'url' => 'file' === $file['type']
                        ? $this->urlGenerator->generate('app_files_proxy', ['filename' => $file['path'], 'preview' => false], UrlGeneratorInterface::ABSOLUTE_URL)
                        : $this->urlGenerator->generate('app_files_index', ['path' => $file['path']]),
                    'previewUrl' => 'file' === $file['type']
                        ? $this->urlGenerator->generate('app_files_proxy', ['filename' => $file['path'], 'preview' => true], UrlGeneratorInterface::ABSOLUTE_URL)
                        : $this->urlGenerator->generate('app_files_index', ['path' => $file['path']]),
                    'relativePath' => $this->getRelativePath($file['path']),
                ];
            }
        }

        // Filtrer par recherche si une recherche est active
        if ('' !== $this->search) {
            $realFiles = $this->searchFiles($realFiles);
        }

        // Tri des fichiers selon les critères sélectionnés
        $this->sortFiles($realFiles);

        return $realFiles;
    }

    private function normalizePath(string $path): string
    {
        // On retire les slashs en début et fin de chaîne
        $path = trim($path, '/');
        // On retire les chemins relatifs
        $path = str_replace('..', '', $path);
        // On retire les . qui sont seul dans la chaîne, en vérifiant qu'il n'y a pas de lettre avant ou après
        $path = preg_replace('/(?<!\w)\.(?!\w)/', '', $path);

        // On retire le point au début de la chaîne
        if (str_starts_with((string) $path, '.')) {
            $path = substr((string) $path, 1);
        }

        return str_replace('//', '/', $path);
    }

    /**
     * @throws FilesystemException
     */
    private function calculateSize($file): int
    {
        // Ne pas calculer la taille pour les performances
        // La taille sera calculée à la demande via AJAX si nécessaire
        return -1; // -1 indique que la taille n'est pas calculée
    }

    private function sortFiles(array &$files): void
    {
        usort($files, function ($a, $b) {
            // Toujours mettre les dossiers en premier, sauf si on trie par type
            if ('type' !== $this->sortBy && $a['type'] !== $b['type']) {
                return 'dir' === $a['type'] ? -1 : 1;
            }

            $result = match ($this->sortBy) {
                'name' => basename((string) $a['path']) <=> basename((string) $b['path']),
                'size' => $a['size'] <=> $b['size'],
                'date' => $a['last_modified'] <=> $b['last_modified'],
                'type' => $this->compareFileTypes($a, $b),
                default => basename((string) $a['path']) <=> basename((string) $b['path'])
            };

            // Si c'est le même type et le même critère de tri, trier par nom en second
            if (0 === $result && 'name' !== $this->sortBy) {
                $result = basename((string) $a['path']) <=> basename((string) $b['path']);
            }

            return 'desc' === $this->sortDirection ? -$result : $result;
        });
    }

    private function compareFileTypes(array $a, array $b): int
    {
        // Les dossiers d'abord
        if ($a['type'] !== $b['type']) {
            return 'dir' === $a['type'] ? -1 : 1;
        }

        // Si ce sont deux dossiers, trier par nom
        if ('dir' === $a['type']) {
            return 0;
        }

        // Pour les fichiers, obtenir leur catégorie de type
        $typeA = $this->getFileTypeCategory(basename((string) $a['path']));
        $typeB = $this->getFileTypeCategory(basename((string) $b['path']));

        return $typeA <=> $typeB;
    }

    private function getFileTypeCategory(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return match ($extension) {
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg' => 'image',
            'mp4', 'avi', 'mov', 'webm' => 'video',
            'mp3', 'wav', 'm4a' => 'audio',
            'pdf' => 'pdf',
            'doc', 'docx', 'odt' => 'document',
            'xlsx', 'xls', 'ods', 'csv' => 'spreadsheet',
            'pptx', 'ppt', 'odp' => 'presentation',
            'zip', 'rar', 'tar', 'gz', '7z' => 'archive',
            'torrent' => 'torrent',
            'txt', 'md' => 'text',
            'html', 'htm', 'css', 'js', 'json', 'xml', 'yaml', 'yml', 'php', 'py', 'java', 'c', 'cpp', 'cs', 'rb', 'go', 'rs' => 'code',
            default => 'other',
        };
    }

    /**
     * @param array<int, array{type: string, path: string, last_modified: int, size: int, url: string, previewUrl: string}> $files
     *
     * @throws FilesystemException
     *
     * @return array<int, array{type: string, path: string, last_modified: int, size: int, url: string, previewUrl: string}>
     */
    private function searchFiles(array $files): array
    {
        $searchTerm = mb_strtolower($this->search);
        $matchedFiles = [];

        foreach ($files as $file) {
            // Rechercher dans le nom du fichier/dossier
            $filename = basename((string) $file['path']);
            if (str_contains(mb_strtolower($filename), $searchTerm)) {
                $matchedFiles[] = $file;
                continue;
            }

            // Si c'est un dossier, rechercher dans son contenu
            if ('dir' === $file['type']) {
                $subFiles = $this->searchInDirectory($file['path'], $searchTerm);
                $matchedFiles = array_merge($matchedFiles, $subFiles);
            }
        }

        return $matchedFiles;
    }

    /**
     * @throws FilesystemException
     *
     * @return array<int, array{type: string, path: string, last_modified: int, size: int, url: string, previewUrl: string}>
     */
    private function searchInDirectory(string $directory, string $searchTerm): array
    {
        $matchedFiles = [];
        $files = $this->defaultAdapter->listContents('/' . $directory, true);

        foreach ($files as $file) {
            $filename = basename((string) $file['path']);

            // Ignorer les fichiers cachés
            if (str_starts_with($filename, '.')) {
                continue;
            }

            // Vérifier les permissions
            $pathFile = explode('/', (string) $file['path']);
            $parentDirectory = $this->parentDirectoryRepository->findOneBy(['name' => $pathFile[0]]);

            if (null === $parentDirectory || !$this->security->isGranted('file_read', $parentDirectory)) {
                continue;
            }

            // Vérifier si le nom correspond
            if (str_contains(mb_strtolower($filename), $searchTerm)) {
                $matchedFiles[] = [
                    'type' => $file['type'],
                    'path' => $file['path'],
                    'last_modified' => $file['lastModified'],
                    'size' => $file['fileSize'] ?? ('dir' === $file['type'] ? $this->calculateSize($file) : 0),
                    'url' => 'file' === $file['type']
                        ? $this->urlGenerator->generate('app_files_proxy', ['filename' => $file['path'], 'preview' => false], UrlGeneratorInterface::ABSOLUTE_URL)
                        : $this->urlGenerator->generate('app_files_index', ['path' => $file['path']]),
                    'previewUrl' => 'file' === $file['type']
                        ? $this->urlGenerator->generate('app_files_proxy', ['filename' => $file['path'], 'preview' => true], UrlGeneratorInterface::ABSOLUTE_URL)
                        : $this->urlGenerator->generate('app_files_index', ['path' => $file['path']]),
                    'relativePath' => $this->getRelativePath($file['path']),
                ];
            }
        }

        return $matchedFiles;
    }

    private function getRelativePath(string $fullPath): string
    {
        if ('' === $this->path) {
            return $fullPath;
        }

        // Retirer le chemin de base pour obtenir le chemin relatif
        $basePath = rtrim($this->path, '/') . '/';
        if (str_starts_with($fullPath, $basePath)) {
            return substr($fullPath, strlen($basePath));
        }

        return $fullPath;
    }

    #[LiveAction]
    public function toggleSort(#[LiveArg] string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = 'asc' === $this->sortDirection ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }
    }

    #[LiveAction]
    public function toggleSelection(#[LiveArg] string $filePath): void
    {
        $index = array_search($filePath, $this->selectedFiles, true);
        if (false !== $index) {
            unset($this->selectedFiles[$index]);
            $this->selectedFiles = array_values($this->selectedFiles);
        } else {
            $this->selectedFiles[] = $filePath;
        }
    }

    #[LiveAction]
    public function toggleSelectAll(): void
    {
        $files = $this->getFiles();
        $allPaths = array_map(static fn ($file) => $file['path'], $files);

        $this->selectedFiles = count($this->selectedFiles) === count($allPaths) ? [] : $allPaths;
    }

    #[LiveAction]
    public function clearSelection(): void
    {
        $this->selectedFiles = [];
    }
}
