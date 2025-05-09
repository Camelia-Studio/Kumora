<?php

namespace App\Controller;

use App\Repository\ParentDirectoryRepository;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class AutocompleteController extends AbstractController
{
    /**
     * @throws FilesystemException
     */
    #[Route('/autocomplete/path/{type}', name: 'chemin_autocomplete')]
    public function autocomplete(Filesystem $defaultAdapter, ParentDirectoryRepository $parentDirectoryRepository, #[MapEntity(id: 'type')]string $type, #[MapQueryParameter('query')] string $q): Response
    {
        if (!in_array($type, ['directory', 'file'], true)) {
            throw $this->createNotFoundException('Type not found');
        }

        $paths = [];

        if ($type === 'directory') {
            $paths[] = '/';
        }

        $files = $defaultAdapter->listContents('/', true);

        foreach ($files as $file) {
            if ($file['type'] === 'dir') {
                $parts = explode('/', $file['path']);

                $parentDirectory = $parentDirectoryRepository->findOneBy(['name' => $parts[0]]);

                if (null !== $parentDirectory && $this->isGranted('file_write', $parentDirectory)) {
                    $paths[] = $file['path'];
                }
            }
        }

        // Filtrer les chemins en fonction de la requête
        $paths = array_filter($paths, function($path) use ($q) {
            return str_contains($path, $q);
        });



        // Retour au format Tom Select
        $data = array_map(function($path) {
            if ($path === '/') {
                return [
                    'value' => $path,
                    'text' => '/',
                ];
            } else {
                return [
                    'value' => $path,
                    'text' => '/' . $path,
                ];
            }
        }, $paths);

        return $this->json([
            'results' => $data,
        ]);
    }
}