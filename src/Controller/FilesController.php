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
                $parentDirectory->setOwnerRole($user->getAccessGroup());
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
            'path' => '/' . $this->normalizePath(dirname($path)),
        ];

        $form = $this->createForm($formType, $newPath);

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

                if ($this->filesystem->fileExists($newPath . '/' . basename($path))) {
                    $form->addError(new FormError("Un fichier du même nom existe déjà !"));

                    return $this->render('files/move.html.twig', [
                        'form' => $form->createView(),
                        'path' => $path,
                        'fileinfo' => $fileInfo,
                    ]);
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

                $this->filesystem->move($path, $newPath . '/' . basename($path));
            } else {
                // Si le dossier existe déjà, on envoie une erreur
                if ($this->filesystem->directoryExists($newPath . '/' . basename($path))) {
                    $form->addError(new FormError("Un dossier du même nom existe déjà !"));

                    return $this->render('files/move.html.twig', [
                        'form' => $form->createView(),
                        'path' => $path,
                        'fileinfo' => $fileInfo,
                    ]);
                }

                $name = explode('/', $this->normalizePath($newPath . '/' . basename($path)))[0];
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

                $this->filesystem->move($path, $newPath . '/' . basename($path));

                if ($parentDir->getName() === $path) {
                    $this->entityManager->remove($parentDir);
                    $this->entityManager->flush();
                }

                $redirectPath = $this->normalizePath($newPath . '/' . basename($path));
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
}
