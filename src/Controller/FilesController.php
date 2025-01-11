<?php

namespace App\Controller;

use App\Form\CreateDirectoryType;
use App\Form\RenameType;
use App\Form\UploadType;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/files', 'app_files_')]
#[IsGranted('ROLE_USER')]
class FilesController extends AbstractController
{
    /**
     * @throws FilesystemException
     */
    #[Route('/', name: 'index')]
    public function index(Filesystem $defaultAdapter, UrlGeneratorInterface $urlGenerator, #[MapQueryParameter('path')] string $path = ''): Response
    {
        $path = $this->normalizePath($path);

        if ($path !== '' && !$defaultAdapter->directoryExists($path)) {
            throw $this->createNotFoundException("Ce dossier n'existe pas !");
        }

        $files = $defaultAdapter->listContents('/' . $path);

        $realFiles = [];
        
        foreach ($files as $file) {
            $filename = basename($file['path']);
            if (!str_starts_with($filename, '.')) {
                $realFiles[] = [
                    'type' => $file['type'],
                    'path' => $file['path'],
                    'last_modified' => $file['lastModified'],
                    'size' => $file['fileSize'] ?? null,
                    'url' => $file['type'] === 'file'
                        ?  $this->generateUrl('app_files_app_file_proxy', ['filename' => $file['path']], UrlGeneratorInterface::ABSOLUTE_URL)
                        :  $this->generateUrl('app_files_index', ['path' => $file['path']]),
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

        return $this->render('files/index.html.twig', [
            'files' => $realFiles,
            'path' => $path,
        ]);
    }

    #[Route('/file-proxy', name: 'app_file_proxy')]
    public function fileProxy(Filesystem $defaultAdapter, #[MapQueryParameter('filename')]string $filename)
    {
        $file = $this->normalizePath($filename);
        $mimetype = $defaultAdapter->mimeType($file);
        if ($mimetype === '') {
            $mimetype = 'application/octet-stream';
        }

        $response = new StreamedResponse(static function () use ($file, $defaultAdapter): void {
            $outputStream = fopen('php://output', 'w');
            $fileStream = $defaultAdapter->readStream($file);
            stream_copy_to_stream($fileStream, $outputStream);
        });

        $response->headers->set('Content-Type', $mimetype);
        $disposition = HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_ATTACHMENT,
            basename($file)
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/file-delete', name: 'delete')]
    public function fileDelete(Filesystem $defaultAdapter, #[MapQueryParameter('filename')] string $filename): RedirectResponse
    {
        $file = $this->normalizePath($filename);

        if ($file !== '' && !str_starts_with($file, '.') && $defaultAdapter->fileExists($file)) {
            $defaultAdapter->delete($file);

            $this->addFlash('success', 'Le fichier a bien été supprimé.');
        } else {
            $this->addFlash('error', 'Le fichier n\'existe pas.');
        }

        return $this->redirectToRoute('app_files_index', [
            'path' => dirname($file),
        ]);
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/directory-delete', name: 'delete_directory')]
    public function directoryDelete(Filesystem $defaultAdapter, #[MapQueryParameter('path')] string $path): RedirectResponse
    {
        $path = $this->normalizePath($path);


        if ($path !== '' && !str_starts_with($path, '.') && $defaultAdapter->directoryExists($path)) {
            $defaultAdapter->deleteDirectory($path);

            $this->addFlash('success', 'Le dossier a bien été supprimé.');
        } else {
            $this->addFlash('error', 'Le dossier n\'existe pas.');
        }

        return $this->redirectToRoute('app_files_index', [
            'path' => dirname($path),
        ]);
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/rename', name: 'rename')]
    public function rename(#[MapQueryParameter('path')] string $filepath, Request $request, Filesystem $defaultAdapter): Response
    {
        $filepath = $this->normalizePath($filepath);

        if ($filepath === '' || str_starts_with($filepath, '.') || !$defaultAdapter->fileExists($filepath)) {
            throw $this->createNotFoundException("Ce fichier n'existe pas !");
        }

        $data = [
            'newName' => pathinfo($filepath, PATHINFO_BASENAME),
        ];
        $form = $this->createForm(RenameType::class, $data);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $newName = $data['newName'];

            $newPath = dirname($filepath) . '/' . $newName;

            $defaultAdapter->move($filepath, $newPath);

            $this->addFlash('success', 'Le fichier a bien été renommé.');

            return $this->redirectToRoute('app_files_index', [
                'path' => dirname($filepath),
            ]);
        }

        return $this->render('files/rename.html.twig', [
            'form' => $form->createView(),
            'filepath' => $filepath,
            'type' => 'fichier',
        ]);
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/create-directory', name: 'create_directory')]
    public function createDirectory(Request $request, Filesystem $defaultAdapter, #[MapQueryParameter('base')] string $basePath): Response
    {
        $basePath = $this->normalizePath($basePath);
        $form = $this->createForm(CreateDirectoryType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $name = $data['name'];

            $defaultAdapter->createDirectory($basePath . '/' . $name);

            $defaultAdapter->write($basePath . '/' . $name . '/.gitkeep', '');

            $this->addFlash('success', 'Le dossier a bien été créé.');

            return $this->redirectToRoute('app_files_index', [
                'path' => $basePath,
            ]);
        }

        return $this->render('files/create_directory.html.twig', [
            'form' => $form->createView(),
            'basePath' => $basePath,
        ]);
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/rename-directory', name: 'rename-directory')]
    public function renameDirectory(#[MapQueryParameter('path')] string $filepath, Request $request, Filesystem $defaultAdapter): Response
    {
        $filepath = $this->normalizePath($filepath);

        if ($filepath === '' || str_starts_with($filepath, '.') || !$defaultAdapter->directoryExists($filepath)) {
            throw $this->createNotFoundException("Ce dossier n'existe pas !");
        }

        $data = [
            'newName' => pathinfo($filepath, PATHINFO_BASENAME),
        ];
        $form = $this->createForm(RenameType::class, $data);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $newName = $data['newName'];

            $newPath = dirname($filepath) . '/' . $newName;

            $defaultAdapter->move($filepath, $newPath);

            $this->addFlash('success', 'Le dossier a bien été renommé.');

            return $this->redirectToRoute('app_files_index', [
                'path' => dirname($filepath),
            ]);
        }

        return $this->render('files/rename.html.twig', [
            'form' => $form->createView(),
            'filepath' => $filepath,
            'type' => 'dossier',
        ]);
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/upload', name: 'upload')]
    public function upload(#[MapQueryParameter('path')] string $path, Request $request, Filesystem $defaultAdapter): Response
    {
        $path = $this->normalizePath($path);

        $form = $this->createForm(UploadType::class);

        if ($path !== '' && !$defaultAdapter->directoryExists($path)) {
            throw $this->createNotFoundException("Ce dossier n'existe pas !");
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $files = $data['files'];

            /**
             * @var UploadedFile $file
             */
            foreach ($files as $file) {
                $filename = $file->getClientOriginalName();
                $defaultAdapter->write($path . '/' . $filename, $file->getContent());
            }

            $this->addFlash('success', 'Les ' . count($files) . ' fichiers ont bien été envoyés.');

            return $this->redirectToRoute('app_files_index', [
                'path' => $path,
            ]);
        }

        return $this->render('files/upload.html.twig', [
            'form' => $form->createView(),
            'path' => $path,
        ]);
    }

    private function normalizePath(string $path): string
    {
        // On retire les slashs en début et fin de chaîne
        $path = trim($path, '/');
        // On retire les chemins relatifs
        $path = str_replace('..', '', $path);
        $path = str_replace('//', '/', $path);

        return $path;
    }
}
