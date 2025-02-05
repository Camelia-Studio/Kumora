<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ParentDirectory;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Form\CreateDirectoryType;
use App\Form\FilePermissionType;
use App\Form\RenameType;
use App\Form\UploadType;
use App\Repository\ParentDirectoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
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
class FilesController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ParentDirectoryRepository $parentDirectoryRepository
    ) {
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/', name: 'index')]
    #[IsGranted('ROLE_USER')]
    public function index(Filesystem $defaultAdapter, UrlGeneratorInterface $urlGenerator, #[MapQueryParameter('path')] string $path = ''): Response
    {
        $path = $this->normalizePath($path);
        $this->getUser();
        if ('' !== $path) {
            $pathExploded = explode('/', $path);

            $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $pathExploded[0]]);

            if (null === $parentDir || !$defaultAdapter->directoryExists($path)) {
                throw $this->createNotFoundException("Ce dossier n'existe pas !");
            }

            if (!$this->isGranted('file_read', $parentDir)) {
                throw $this->createNotFoundException("Vous n'avez pas le droit d'accéder à ce dossier !");
            }
        }

        $files = $defaultAdapter->listContents('/' . $path);

        $realFiles = [];

        foreach ($files as $file) {
            $filename = basename((string) $file['path']);
            if (!str_starts_with($filename, '.')) {
                // On vérifie si l'utilisateur a le droit d'accéder au fichier (vérifier que owner_role du parentDirectory correspondant est bien le folderRole de l'utilisateur)
                $pathFile = explode('/', (string) $file['path']);
                if ('' !== $path) {
                    $parentDirectory = $this->parentDirectoryRepository->findOneBy(['name' => $pathFile[0]]);

                    if (null === $parentDirectory || !$this->isGranted('file_read', $parentDirectory)) {
                        continue;
                    }
                } elseif ('file' !== $file['type']) {
                    $parentDirectory = $this->parentDirectoryRepository->findOneBy(['name' => $filename]);

                    if (null === $parentDirectory || !$this->isGranted('file_read', $parentDirectory)) {
                        continue;
                    }
                }

                $realFiles[] = [
                    'type' => $file['type'],
                    'path' => $file['path'],
                    'last_modified' => $file['lastModified'],
                    'size' => $file['fileSize'] ?? null,
                    'url' => 'file' === $file['type']
                        ? $this->generateUrl('app_files_app_file_proxy', ['filename' => $file['path']], UrlGeneratorInterface::ABSOLUTE_URL)
                        : $this->generateUrl('app_files_index', ['path' => $file['path']]),
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

        return $this->render('files/index.html.twig', [
            'files' => $realFiles,
            'path' => $path,
            'parentDir' => $parentDir ?? null,
        ]);
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/file-proxy', name: 'app_file_proxy')]
    public function fileProxy(Filesystem $defaultAdapter, #[MapQueryParameter('filename')] string $filename)
    {
        $file = $this->normalizePath($filename);

        $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => explode('/', $file)[0]]);

        if (null === $parentDir) {
            throw $this->createNotFoundException("Vous n'avez pas le droit d'accéder à ce fichier !");
        }

        // Si l'owner role sur le parent est visiteur, on peut accéder au fichier sans être connecté
        if (!$this->isGranted('file_read', $parentDir)) {
            throw $this->createNotFoundException("Vous n'avez pas le droit d'accéder à ce fichier !");
        }

        $mimetype = $defaultAdapter->mimeType($file);
        if ('' === $mimetype) {
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
    #[IsGranted('ROLE_USER')]
    public function fileDelete(Filesystem $defaultAdapter, #[MapQueryParameter('filename')] string $filename): RedirectResponse
    {
        $this->getUser();
        $file = $this->normalizePath($filename);

        $realPath = explode('/', $file);
        $parentDir = null;

        if (count($realPath) > 1) {
            $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $realPath[0]]);

            if (null === $parentDir  || !$this->isGranted('file_write', $parentDir)) {
                throw $this->createNotFoundException("Vous n'avez pas le droit de supprimer ce fichier !");
            }
        }

        if ('' !== $file && !str_starts_with($file, '.') && $defaultAdapter->fileExists($file)) {
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
    #[IsGranted('ROLE_USER')]
    public function directoryDelete(Filesystem $defaultAdapter, #[MapQueryParameter('path')] string $path): RedirectResponse
    {
        $path = $this->normalizePath($path);
        $this->getUser();

        $realPath = explode('/', $path);
        $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $realPath[0]]);

        if (null === $parentDir || !$this->isGranted('file_write', $parentDir)) {
            throw $this->createNotFoundException("Vous n'avez pas le droit de supprimer ce dossier !");
        }

        if ('' !== $path && !str_starts_with($path, '.') && $defaultAdapter->directoryExists($path)) {
            $defaultAdapter->deleteDirectory($path);
            if ($parentDir->getName() === $path) {
                $this->entityManager->remove($parentDir);
                $this->entityManager->flush();
            }

            $this->addFlash('success', 'Le dossier a bien été supprimé.');
        } else {
            $this->addFlash('error', 'Le dossier n\'existe pas.');
        }

        $newPath = dirname($path);

        if ('.' === $newPath) {
            $newPath = '';
        }

        return $this->redirectToRoute('app_files_index', [
            'path' => $newPath,
        ]);
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/rename', name: 'rename')]
    #[IsGranted('ROLE_USER')]
    public function rename(#[MapQueryParameter('path')] string $filepath, Request $request, Filesystem $defaultAdapter): Response
    {
        $filepath = $this->normalizePath($filepath);
        $this->getUser();

        if ('' === $filepath || str_starts_with($filepath, '.') || !$defaultAdapter->fileExists($filepath)) {
            throw $this->createNotFoundException("Ce fichier n'existe pas !");
        }

        $realPath = explode('/', $filepath);

        if (count($realPath) > 1) {
            $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $realPath[0]]);

            if (null === $parentDir || !$this->isGranted('file_write', $parentDir)) {
                throw $this->createNotFoundException("Vous n'avez pas le droit de renommer ce fichier !");
            }
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
    #[IsGranted('ROLE_USER')]
    public function createDirectory(Request $request, Filesystem $defaultAdapter, #[MapQueryParameter('base')] string $basePath): Response
    {
        $basePath = $this->normalizePath($basePath);
        $realPath = explode('/', $basePath);
        /**
         * @var User $user
         */
        $user = $this->getUser();

        if (count($realPath) > 1) {
            $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $realPath[0]]);
            if (null === $parentDir || !$this->isGranted('file_write', $parentDir)) {
                throw $this->createNotFoundException("Vous n'avez pas le droit de créer de sous-dossier dans ce dossier !");
            }
        }
        $form = $this->createForm(CreateDirectoryType::class);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $name = $data['name'];

            if (count(explode('/', (string) $name)) > 1) {
                $name = explode('/', (string) $name)[0];
            }

            if ($defaultAdapter->directoryExists($basePath . '/' . $name)) {
                $this->addFlash('error', 'Le dossier existe déjà.');

                return $this->redirectToRoute('app_files_index', [
                    'path' => $basePath,
                ]);
            }

            $defaultAdapter->createDirectory($basePath . '/' . $name);

            $defaultAdapter->write($basePath . '/' . $name . '/.gitkeep', '');

            // si basePath est vide, on crée un parentDirectory
            if ('' === $basePath) {
                /**
                 * @var User $user
                 */
                $user = $this->getUser();
                $parentDirectory = new ParentDirectory();
                $parentDirectory->setName($name);
                $parentDirectory->setOwnerRole($user->getFolderRole());
                $parentDirectory->setIsPublic(false);
                $parentDirectory->setUserCreated($user);

                $this->entityManager->persist($parentDirectory);
                $this->entityManager->flush();
            }

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
    #[IsGranted('ROLE_USER')]
    public function renameDirectory(#[MapQueryParameter('path')] string $filepath, Request $request, Filesystem $defaultAdapter): Response
    {
        $filepath = $this->normalizePath($filepath);
        $this->getUser();

        $realPath = explode('/', $filepath);
        $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $realPath[0]]);

        if (null === $parentDir || !$this->isGranted('file_write', $parentDir)) {
            throw $this->createNotFoundException("Vous n'avez pas le droit de renommer ce dossier !");
        }

        if ('' === $filepath || str_starts_with($filepath, '.') || !$defaultAdapter->directoryExists($filepath)) {
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
    #[IsGranted('ROLE_USER')]
    public function upload(#[MapQueryParameter('path')] string $path, Request $request, Filesystem $defaultAdapter): Response
    {
        $path = $this->normalizePath($path);

        $this->getUser();

        if ('' === $path) {
            throw $this->createNotFoundException("Vous ne pouvez pas uploader de fichier à la racine !");
        }

        $realPath = explode('/', $path);
        $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $realPath[0]]);

        if (null === $parentDir || !$this->isGranted('file_write', $parentDir)) {
            throw $this->createNotFoundException("Vous n'avez pas le droit d'uploader des fichiers dans ce dossier !");
        }

        $form = $this->createForm(UploadType::class);

        if (!$defaultAdapter->directoryExists($path)) {
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
        // On retire les . qui sont seul dans la chaîne, en vérifiant qu'il n'y a pas de lettre avant ou après
        $path = preg_replace('/(?<!\w)\.(?!\w)/', '', $path);

        return str_replace('//', '/', $path);
    }

    #[Route('/file-edit-permission/{parentDir}', name: 'file_edit_permission')]
    #[IsGranted('ROLE_USER')]
    public function fileRead(#[MapEntity(mapping: ['parentDir' => 'name'])] ParentDirectory $parentDir, Request $request): Response
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();

        // 2 possibilités : soit l'utilisateur est le créateur du dossier, soit le dossier est public et l'utilisateur a le role Conseil d'administration
        // Si ce n'est pas le cas, on redirige vers la page d'accueil
        if ($parentDir->getUserCreated() !== $user) {
            if ($parentDir->isPublic() && RoleEnum::CONSEIL_ADMINISTRATION !== $user->getFolderRole()) {
                $this->addFlash('error', 'Vous n\'avez pas le droit de modifier les permissions de ce dossier.');
                return $this->redirectToRoute('app_files_index');
            } elseif (!$parentDir->isPublic()) {
                $this->addFlash('error', 'Vous n\'avez pas le droit de modifier les permissions de ce dossier.');
                return $this->redirectToRoute('app_files_index');
            }
        }

        $form = $this->createForm(FilePermissionType::class, $parentDir);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();

            foreach ($datas->getParentDirectoryPermissions() as $parentPerm) {
                $this->entityManager->persist($parentPerm);
            }
            $this->entityManager->persist($datas);

            $this->entityManager->flush();

            $this->addFlash('success', 'Les permissions ont bien été modifiées.');

            return $this->redirectToRoute('app_files_index');
        }

        return $this->render('files/file_edit.html.twig', [
            'parentDir' => $parentDir,
            'form' => $form->createView(),
        ]);
    }
}
