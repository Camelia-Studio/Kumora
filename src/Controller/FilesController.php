<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ParentDirectory;
use App\Entity\User;
use App\Form\CreateDirectoryType;
use App\Form\FilePermissionType;
use App\Form\MoveFileType;
use App\Form\MoveType;
use App\Form\RenameType;
use App\Form\UploadType;
use App\Repository\AccessGroupRepository;
use App\Repository\ParentDirectoryRepository;
use App\Service\UserActionLogger;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
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

class FilesController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface    $entityManager,
        private readonly ParentDirectoryRepository $parentDirectoryRepository,
        private readonly AccessGroupRepository     $accessGroupRepository,
        private readonly Filesystem $filesystem,
        private readonly UserActionLogger $actionLogger,
        private readonly string $projectDir,
    ) {
    }
    /**
     * @throws FilesystemException
     */
    #[Route('/files/', name: 'app_files_index')]
    #[IsGranted('ROLE_USER')]
    public function index(UrlGeneratorInterface $urlGenerator, #[MapQueryParameter('path')] string $path = ''): Response
    {
        return $this->render('files/index.html.twig', [
            'path' => $this->normalizePath($path),
        ]);
    }
    /**
     * @throws FilesystemException
     */
    #[Route('/files/file-proxy', name: 'app_files_proxy')]
    public function fileProxy(Filesystem $defaultAdapter, #[MapQueryParameter('filename')] string $filename, #[MapQueryParameter('preview')] bool $preview)
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

        if (!$preview) {
            $disposition = HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_ATTACHMENT,
                preg_replace('/[\x00-\x1F\x80-\xFF]/', '', basename($file)),
            );
            $response->headers->set('Content-Disposition', $disposition);
        }

        return $response;
    }
    /**
     * @throws FilesystemException
     */
    #[Route('/files/file-delete', name: 'app_files_delete')]
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

            $this->actionLogger->logFileDelete($file);

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
    #[Route('/files/directory-delete', name: 'app_files_delete_directory')]
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

            $this->actionLogger->logFolderDelete($path);

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
    #[Route('/files/rename', name: 'app_files_rename')]
    #[IsGranted('ROLE_USER')]
    public function rename(#[MapQueryParameter('path')] string $filepath, Request $request, Filesystem $defaultAdapter): Response
    {
        $filepath = $this->normalizePath($filepath);

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

            $this->actionLogger->logFileRename(
                pathinfo($filepath, PATHINFO_BASENAME),
                $newName,
                $newPath
            );

            $this->addFlash('success', 'Le fichier a bien été renommé.');

            return $this->redirectToRoute('app_files_index', [
                'path' => dirname($filepath),
            ]);
        }

        return $this->render('files/rename.html.twig', [
            'form' => $form->createView(),
            'filepath' => $filepath,
            'type' => 'fichier',
            'path' => dirname($filepath),
        ]);
    }
    /**
     * @throws FilesystemException
     */
    #[Route('/files/create-directory', name: 'app_files_create_directory')]
    #[IsGranted('ROLE_USER')]
    public function createDirectory(Request $request, Filesystem $defaultAdapter, #[MapQueryParameter('base')] string $basePath): Response
    {
        $basePath = $this->normalizePath($basePath);
        $realPath = explode('/', $basePath);

        if (count($realPath) > 1) {
            $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $realPath[0]]);
            if (null === $parentDir || !$this->isGranted('file_write', $parentDir)) {
                throw $this->createNotFoundException("Vous n'avez pas le droit de créer de sous-dossier dans ce dossier !");
            }
        }
        $isRootDirectory = '' === $basePath;
        $form = $this->createForm(CreateDirectoryType::class, null, [
            'is_root_directory' => $isRootDirectory,
        ]);

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

            // Log de l'action de création de dossier
            $folderPath = '' === $basePath ? $name : $basePath . '/' . $name;
            $this->actionLogger->logFolderCreate($folderPath);

            // si basePath est vide, on crée un parentDirectory
            if ($isRootDirectory) {
                /**
                 * @var User $user
                 */
                $user = $this->getUser();
                $parentDirectory = new ParentDirectory();
                $parentDirectory->setName($name);
                $parentDirectory->setUserCreated($user);

                // Récupérer les données du formulaire pour les permissions
                $ownerRole = $form->get('ownerRole')->getData();
                $typeDossier = $form->get('typeDossier')->getData();
                $permissions = $form->get('parentDirectoryPermissions')->getData();

                $parentDirectory->setOwnerRole($ownerRole ?? $user->getAccessGroup());
                $parentDirectory->setIsPublic('shared' === $typeDossier);

                // Ajouter les permissions supplémentaires
                if (is_iterable($permissions)) {
                    foreach ($permissions as $permission) {
                        $permission->setParentDirectory($parentDirectory);
                        $parentDirectory->addParentDirectoryPermission($permission);
                    }
                }

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
            'isRootDirectory' => $isRootDirectory,
        ]);
    }
    /**
     * @throws FilesystemException
     */
    #[Route('/files/rename-directory', name: 'app_files_rename-directory')]
    #[IsGranted('ROLE_USER')]
    public function renameDirectory(#[MapQueryParameter('path')] string $filepath, Request $request, Filesystem $defaultAdapter): Response
    {
        $filepath = $this->normalizePath($filepath);

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

            // Si c'est un parent directory, on renomme le parent directory dans la base de données
            if ($parentDir->getName() === $filepath) {
                $parentDir->setName($newName);
                $this->entityManager->persist($parentDir);
                $this->entityManager->flush();
            }

            $defaultAdapter->move($filepath, $newPath);

            $this->actionLogger->logFolderRename(
                pathinfo($filepath, PATHINFO_BASENAME),
                $newName,
                $newPath
            );

            $this->addFlash('success', 'Le dossier a bien été renommé.');

            return $this->redirectToRoute('app_files_index', [
                'path' => dirname($filepath),
            ]);
        }

        return $this->render('files/rename.html.twig', [
            'form' => $form->createView(),
            'filepath' => $filepath,
            'type' => 'dossier',
            'path' => $this->normalizePath(dirname($filepath)),
        ]);
    }
    /**
     * @throws FilesystemException
     */
    #[Route('/files/upload', name: 'app_files_upload')]
    #[IsGranted('ROLE_USER')]
    public function upload(#[MapQueryParameter('path')] string $path, Request $request, Filesystem $defaultAdapter): Response
    {
        $path = $this->normalizePath($path);

        $this->getUser();

        if ('' === $path) {
            throw $this->createNotFoundException("Vous ne pouvez pas téléverser de fichier à la racine !");
        }

        $realPath = explode('/', $path);
        $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $realPath[0]]);

        if (null === $parentDir || !$this->isGranted('file_write', $parentDir)) {
            throw $this->createNotFoundException("Vous n'avez pas le droit de téléverser des fichiers dans ce dossier !");
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
                $file->move($this->projectDir . '/uploads/' . $path, $filename);

                // Log de l'action d'upload
                $filePath = $path . '/' . $filename;
                $this->actionLogger->logFileUpload($filePath, $file);
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
    #[Route('/files/file-edit-permission/{parentDir}', name: 'app_files_file_edit_permission')]
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
            if ($parentDir->isPublic() && $this->accessGroupRepository->getHighestRole() !== $user->getAccessGroup()) {
                $this->addFlash('error', 'Vous n\'avez pas le droit de modifier les permissions de ce dossier.');
                return $this->redirectToRoute('app_files_index');
            } elseif (!$parentDir->isPublic()) {
                $this->addFlash('error', 'Vous n\'avez pas le droit de modifier les permissions de ce dossier.');
                return $this->redirectToRoute('app_files_index');
            }
        }

        $form = $this->createForm(FilePermissionType::class, $parentDir);

        $form->get('typeDossier')->setData($parentDir->isPublic() ? 'shared' : 'private');

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $datas = $form->getData();
            $typeDossier = $form->get('typeDossier')->getData();

            $oldPermissions = $parentDir->isPublic() ? 'public' : 'private';
            $newPermissions = 'shared' === $typeDossier ? 'public' : 'private';

            if ('shared' === $typeDossier) {
                $datas->setIsPublic(true);
            } else {
                $datas->setIsPublic(false);
            }

            foreach ($datas->getParentDirectoryPermissions() as $parentPerm) {
                $this->entityManager->persist($parentPerm);
            }
            $this->entityManager->persist($datas);

            $this->entityManager->flush();

            $this->actionLogger->logPermissionChange(
                $parentDir->getName(),
                $oldPermissions,
                $newPermissions
            );

            $this->addFlash('success', 'Les permissions ont bien été modifiées.');

            return $this->redirectToRoute('app_files_index');
        }

        return $this->render('files/file_edit.html.twig', [
            'parentDir' => $parentDir,
            'form' => $form->createView(),
        ]);
    }
    /**
     * @throws FilesystemException
     */
    #[Route('/files/move', name: 'app_files_move')]
    #[IsGranted('ROLE_USER')]
    public function move(#[MapQueryParameter('path')] string $path, Request $request): Response
    {
        $path = $this->normalizePath($path);

        $realPath = explode('/', $path);
        $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $realPath[0]]);

        if (null === $parentDir || !$this->isGranted('file_write', $parentDir)) {
            throw $this->createNotFoundException("Vous n'avez pas le droit de déplacer ce fichier !");
        }
        $fileInfo = [];

        if ($this->filesystem->fileExists($path)) {
            $fileInfo['type'] = 'file';
            $formType = MoveFileType::class;
        } elseif ($this->filesystem->directoryExists($path)) {
            $fileInfo['type'] = 'directory';
            $formType = MoveType::class;
        } else {
            throw $this->createNotFoundException("Ce fichier ou dossier n'existe pas !");
        }

        $newPath = [
            'path' => '',
        ];

        // Construire la liste des chemins à exclure
        $excludePaths = [];

        if ('directory' === $fileInfo['type']) {
            // Exclure le dossier en cours de déplacement
            $excludePaths[] = $path;
        }

        // Ajouter le dossier parent du fichier/dossier
        $parentDir = $this->normalizePath(dirname($path));
        if ('' !== $parentDir) {
            $excludePaths[] = $parentDir;
        }

        // Construire l'URL d'autocomplétion avec les chemins à exclure
        $autocompleteUrl = null;
        if (count($excludePaths) > 0) {
            $autocompleteUrl = '/kumora/autocomplete/path/file?exclude=' . urlencode(json_encode($excludePaths, JSON_UNESCAPED_SLASHES));
        }

        $form = $this->createForm($formType, $newPath, $autocompleteUrl ? ['autocomplete_url' => $autocompleteUrl] : []);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $newPath = $this->normalizePath($data['path']);

            $redirectPath = $this->normalizePath($newPath);

            if ('file' === $fileInfo['type']) {
                if ("" === $redirectPath) {
                    $form->addError(new FormError("Tu ne peux pas déplacer ce fichier à la racine !"));

                    return $this->render('files/move.html.twig', [
                        'form' => $form->createView(),
                        'path' => $path,
                        'fileinfo' => $fileInfo,
                    ]);
                }

                // Vérifier si le fichier existe déjà et générer un nouveau nom si nécessaire
                $finalPath = $newPath . '/' . basename($path);
                if ($this->filesystem->fileExists($finalPath)) {
                    $finalPath = $this->generateUniqueFilename($this->filesystem, $newPath, basename($path));
                }

                $name = explode('/', $newPath)[0];
                $parentDirectory = $this->parentDirectoryRepository->findOneBy(['name' => $name]);

                if ($parentDirectory instanceof ParentDirectory && !$this->isGranted('file_write', $parentDirectory)) {
                    $form->addError(new FormError("Vous n'avez pas le droit de déplacer ce fichier dans ce dossier !"));

                    return $this->render('files/move.html.twig', [
                        'form' => $form->createView(),
                        'path' => $path,
                        'fileinfo' => $fileInfo,
                    ]);
                } elseif (null === $parentDirectory) {
                    /**
                     * @var User $user
                     */
                    $user = $this->getUser();
                    $parentDirectory = new ParentDirectory();
                    $parentDirectory->setName($name);
                    $parentDirectory->setOwnerRole($user->getAccessGroup());
                    $parentDirectory->setIsPublic(true);
                    $parentDirectory->setUserCreated($user);
                    $this->entityManager->persist($parentDirectory);
                    $this->entityManager->flush();
                }

                $this->filesystem->move($path, $finalPath);

                $this->actionLogger->logFileMove(
                    $path,
                    $finalPath
                );
            } else {
                // Vérifier si le dossier existe déjà et générer un nouveau nom si nécessaire
                $finalPath = $newPath . '/' . basename($path);
                if ($this->filesystem->directoryExists($finalPath)) {
                    $finalPath = $this->generateUniqueFilename($this->filesystem, $newPath, basename($path));
                }

                $name = explode('/', $this->normalizePath($finalPath))[0];
                $parentDirectory = $this->parentDirectoryRepository->findOneBy(['name' => $name]);

                if ($parentDirectory instanceof ParentDirectory && !$this->isGranted('file_write', $parentDirectory)) {
                    $form->addError(new FormError("Vous n'avez pas le droit de déplacer ce dossier dans ce dossier !"));

                    return $this->render('files/move.html.twig', [
                        'form' => $form->createView(),
                        'path' => $path,
                        'fileinfo' => $fileInfo,
                    ]);
                } elseif (null === $parentDirectory) {
                    /**
                     * @var User $user
                     */
                    $user = $this->getUser();
                    $parentDirectory = new ParentDirectory();
                    $parentDirectory->setName($name);
                    $parentDirectory->setOwnerRole($user->getAccessGroup());
                    $parentDirectory->setIsPublic(true);
                    $parentDirectory->setUserCreated($user);
                    $this->entityManager->persist($parentDirectory);
                    $this->entityManager->flush();
                }

                $this->filesystem->move($path, $finalPath);

                $this->actionLogger->logFolderMove(
                    $path,
                    $finalPath
                );

                if ($parentDir->getName() === $path) {
                    $this->entityManager->remove($parentDir);
                    $this->entityManager->flush();
                }

                $redirectPath = $this->normalizePath($finalPath);
            }

            return $this->redirectToRoute('app_files_index', [
                'path' => $redirectPath,
            ]);
        }

        $folderPath = $this->normalizePath(dirname($path));

        return $this->render('files/move.html.twig', [
            'form' => $form->createView(),
            'path' => $path,
            'fileinfo' => $fileInfo,
            'folderPath' => $folderPath,
        ]);
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/files/bulk-delete', name: 'app_files_bulk_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function bulkDelete(Request $request, Filesystem $defaultAdapter): RedirectResponse
    {
        $files = json_decode((string) $request->request->get('files', '[]'), true);
        $currentPath = $request->request->get('currentPath', '');

        if (!is_array($files) || 0 === count($files)) {
            $this->addFlash('error', 'Aucun fichier sélectionné.');

            return $this->redirectToRoute('app_files_index', ['path' => $currentPath]);
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($files as $file) {
            $file = $this->normalizePath((string) $file);

            if ('' === $file || str_starts_with($file, '.')) {
                continue;
            }

            // Vérifier les permissions
            $realPath = explode('/', $file);
            if (count($realPath) > 1) {
                $parentDir = $this->parentDirectoryRepository->findOneBy(['name' => $realPath[0]]);

                if (null === $parentDir || !$this->isGranted('file_write', $parentDir)) {
                    $errors[] = basename($file) . ' (permissions insuffisantes)';
                    continue;
                }
            }

            try {
                if ($defaultAdapter->fileExists($file)) {
                    $defaultAdapter->delete($file);
                    $this->actionLogger->logFileDelete($file);
                    ++$deletedCount;
                } elseif ($defaultAdapter->directoryExists($file)) {
                    // Vérifier si c'est un ParentDirectory (dossier à la racine)
                    $isRootDirectory = !str_contains($file, '/');

                    if ($isRootDirectory) {
                        // Supprimer le ParentDirectory de la base de données
                        $parentDirEntity = $this->parentDirectoryRepository->findOneBy(['name' => $file]);
                        if (null !== $parentDirEntity) {
                            $this->entityManager->remove($parentDirEntity);
                            $this->entityManager->flush();
                        }
                    }

                    $defaultAdapter->deleteDirectory($file);
                    $this->actionLogger->logFolderDelete($file);
                    ++$deletedCount;
                }
            } catch (\Exception) {
                $errors[] = basename($file) . ' (erreur lors de la suppression)';
            }
        }

        if ($deletedCount > 0) {
            $this->addFlash('success', sprintf('%d élément(s) supprimé(s) avec succès.', $deletedCount));
        }

        if (count($errors) > 0) {
            $this->addFlash('error', 'Erreurs: ' . implode(', ', $errors));
        }

        return $this->redirectToRoute('app_files_index', ['path' => $currentPath]);
    }

    /**
     * @throws FilesystemException
     */
    #[Route('/files/bulk-move', name: 'app_files_bulk_move', methods: ['POST', 'GET'])]
    #[IsGranted('ROLE_USER')]
    public function bulkMove(Request $request, Filesystem $defaultAdapter): Response
    {
        // Distinguer la sélection initiale de la soumission du formulaire
        if ($request->isMethod('POST') && $request->request->has('files') && !$request->request->has('move_file')) {
            $files = json_decode((string) $request->request->get('files', '[]'), true);
            $currentPath = $request->request->get('currentPath', '');

            if (!is_array($files) || 0 === count($files)) {
                $this->addFlash('error', 'Aucun fichier sélectionné.');

                return $this->redirectToRoute('app_files_index', ['path' => $currentPath]);
            }

            // Nettoyer puis stocker les fichiers sélectionnés en session
            $request->getSession()->remove('bulk_move_files');
            $request->getSession()->remove('bulk_move_source_path');
            $request->getSession()->set('bulk_move_files', $files);
            $request->getSession()->set('bulk_move_source_path', $currentPath);

            return $this->redirectToRoute('app_files_bulk_move');
        }

        $files = $request->getSession()->get('bulk_move_files', []);
        $sourcePath = $request->getSession()->get('bulk_move_source_path', '');

        if (!is_array($files) || 0 === count($files)) {
            $this->addFlash('error', 'Aucun fichier à déplacer.');

            return $this->redirectToRoute('app_files_index');
        }

        // Construire la liste des chemins à exclure (fichiers/dossiers sélectionnés + leurs dossiers parents)
        $excludePaths = [];
        foreach ($files as $file) {
            // Ajouter le fichier/dossier lui-même
            $excludePaths[] = $file;

            // Ajouter le dossier parent du fichier
            $parentDir = $this->normalizePath(dirname((string) $file));
            if ('' !== $parentDir && !in_array($parentDir, $excludePaths, true)) {
                $excludePaths[] = $parentDir;
            }
        }

        // Construire l'URL d'autocomplétion avec les chemins à exclure
        $autocompleteUrl = '/kumora/autocomplete/path/file?exclude=' . urlencode(json_encode($excludePaths, JSON_UNESCAPED_SLASHES));

        $form = $this->createForm(MoveFileType::class, null, [
            'autocomplete_url' => $autocompleteUrl,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $destination = $form->get('path')->getData();
            $destination = $this->normalizePath($destination);

            if (!$defaultAdapter->directoryExists($destination)) {
                $this->addFlash('error', 'Le dossier de destination n\'existe pas.');

                return $this->redirectToRoute('app_files_bulk_move');
            }

            $movedCount = 0;
            $errors = [];

            foreach ($files as $file) {
                $file = $this->normalizePath((string) $file);

                if ('' === $file || str_starts_with($file, '.')) {
                    continue;
                }

                // Vérifier qu'on ne déplace pas un dossier dans lui-même ou ses sous-dossiers
                if ($defaultAdapter->directoryExists($file) && str_starts_with($destination . '/', $file . '/')) {
                    $errors[] = basename($file) . ' (impossible de déplacer un dossier dans lui-même)';
                    continue;
                }

                // Vérifier les permissions source
                $realPath = explode('/', $file);
                if (count($realPath) > 1) {
                    $sourceParentDir = $this->parentDirectoryRepository->findOneBy(['name' => $realPath[0]]);

                    if (null === $sourceParentDir || !$this->isGranted('file_write', $sourceParentDir)) {
                        $errors[] = basename($file) . ' (permissions source insuffisantes)';
                        continue;
                    }
                }

                // Vérifier les permissions destination
                $destPath = explode('/', $destination);
                if (count($destPath) > 0) {
                    $destParentDir = $this->parentDirectoryRepository->findOneBy(['name' => $destPath[0]]);

                    if (null === $destParentDir || !$this->isGranted('file_write', $destParentDir)) {
                        $errors[] = basename($file) . ' (permissions destination insuffisantes)';
                        continue;
                    }
                }

                try {
                    $fileName = basename($file);
                    $newPath = $destination . '/' . $fileName;

                    if ($defaultAdapter->fileExists($file)) {
                        // Vérifier si le fichier existe déjà et générer un nouveau nom si nécessaire
                        if ($defaultAdapter->fileExists($newPath)) {
                            $newPath = $this->generateUniqueFilename($defaultAdapter, $destination, $fileName);
                        }
                        $defaultAdapter->move($file, $newPath);
                        $this->actionLogger->logFileMove($file, $newPath);
                        ++$movedCount;
                    } elseif ($defaultAdapter->directoryExists($file)) {
                        // Vérifier si le dossier existe déjà et générer un nouveau nom si nécessaire
                        if ($defaultAdapter->directoryExists($newPath)) {
                            $newPath = $this->generateUniqueFilename($defaultAdapter, $destination, $fileName);
                        }
                        // Pour les dossiers, on doit les copier puis supprimer l'original
                        $this->copyDirectory($defaultAdapter, $file, $newPath);
                        $defaultAdapter->deleteDirectory($file);
                        $this->actionLogger->logFolderMove($file, $newPath);
                        ++$movedCount;
                    }
                } catch (\Exception) {
                    $errors[] = basename($file) . ' (erreur lors du déplacement)';
                }
            }

            // Nettoyer la session
            $request->getSession()->remove('bulk_move_files');
            $request->getSession()->remove('bulk_move_source_path');

            if ($movedCount > 0) {
                $this->addFlash('success', sprintf('%d élément(s) déplacé(s) avec succès.', $movedCount));
            }

            if (count($errors) > 0) {
                $this->addFlash('error', 'Erreurs: ' . implode(', ', $errors));
            }

            return $this->redirectToRoute('app_files_index', ['path' => $destination]);
        }

        return $this->render('files/bulk_move.html.twig', [
            'form' => $form->createView(),
            'files' => $files,
            'sourcePath' => $sourcePath,
        ]);
    }

    /**
     * @throws FilesystemException
     */
    private function copyDirectory(Filesystem $filesystem, string $source, string $destination): void
    {
        $files = $filesystem->listContents($source, true);

        foreach ($files as $file) {
            $path = $file['path'];
            $newPath = str_replace($source, $destination, $path);

            if ('file' === $file['type']) {
                $content = $filesystem->read($path);
                $filesystem->write($newPath, $content);
            } else {
                $filesystem->createDirectory($newPath);
            }
        }
    }

    /**
     * Génère un nom de fichier unique en ajoutant un suffixe si nécessaire.
     */
    private function generateUniqueFilename(Filesystem $filesystem, string $directory, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        $counter = 1;

        do {
            $newFilename = $nameWithoutExt . '_' . $counter . ('' === $extension ? '' : '.' . $extension);
            $newPath = $directory . '/' . $newFilename;
            ++$counter;
        } while ($filesystem->fileExists($newPath) || $filesystem->directoryExists($newPath));

        return $newPath;
    }
}
