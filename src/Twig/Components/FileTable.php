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
                ];
            }
        }

        // On trie par type puis par nom
        usort($realFiles, static function ($a, $b) {
            if ($a['type'] === $b['type']) {
                return $a['path'] <=> $b['path'];
            }
            return $a['type'] <=> $b['type'];
        });

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
        $folderPath = $file['path'];
        // On récupère tout les fichiers dans le dossier
        $files = $this->defaultAdapter->listContents($folderPath, true);

        $size = 0;

        foreach ($files as $fil) {
            $size += $fil['fileSize'] ?? 0;
        }

        return $size;
    }
}
