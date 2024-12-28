<?php

namespace App\Controller;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/files', 'app_files_')]
#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    /**
     * @throws FilesystemException
     */
    #[Route('/', name: 'index')]
    public function index(Filesystem $defaultAdapter, UrlGeneratorInterface $urlGenerator, #[MapQueryParameter('path')] string $path = ''): Response
    {
        // On retire les slashs en début et fin de chaîne
        $path = trim($path, '/');
        // On retire les chemins relatifs
        $path = str_replace('..', '', $path);
        $path = str_replace('//', '/', $path);

        if ($path !== '' && !$defaultAdapter->directoryExists($path)) {
            throw $this->createNotFoundException("Ce dossier n'existe pas !");
        }


        $files = $defaultAdapter->listContents('/' . $path);

        $realFiles = [];
        
        foreach ($files as $file) {
            if (!str_starts_with($file['path'], '.')) {
                $realFiles[] = [
                    'type' => $file['type'],
                    'path' => $file['path'],
                    'last_modified' => $file['lastModified'],
                    'size' => $file['fileSize'] ?? null,
                    'url' => $file['type'] === 'file'
                        ?  $this->generateUrl('app_files_app_file_proxy', ['filename' => $file['path']], UrlGeneratorInterface::ABSOLUTE_URL)
                        :  $this->generateUrl('app_files_index', ['path' => $path . '/' . $file['path']]),
                ];
            }
        }

        // On trie par type puis par nom
        usort($realFiles, static function ($a, $b) {
            if ($a['type'] === $b['type']) {
                return $a['path'] <=> $b['path'];
            } else {
                return $a['type'] <=> $b['type'];
            }
        });

        return $this->render('dashboard/index.html.twig', [
            'files' => $realFiles,
            'path' => $path,
        ]);
    }

    #[Route('/file-proxy', name: 'app_file_proxy')]
    public function fileProxy(Filesystem $defaultAdapter, #[MapQueryParameter('filename')]string $filename)
    {
        $mimetype = $defaultAdapter->mimeType($filename);
        if ($mimetype === '') {
            $mimetype = 'application/octet-stream';
        }

        $response = new StreamedResponse(static function () use ($filename, $defaultAdapter): void {
            $outputStream = fopen('php://output', 'w');
            $fileStream = $defaultAdapter->readStream($filename);
            stream_copy_to_stream($fileStream, $outputStream);
        });

        $response->headers->set('Content-Type', $mimetype);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            basename($filename)
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;

    }
}
