<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\EmailDTO;
use App\DTO\PasswordDTO;
use App\Entity\User;
use App\Form\AvatarFormType;
use App\Form\EmailFormType;
use App\Form\PasswordFormType;
use App\Repository\UserActionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserActionRepository $userActionRepository,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
    }

    #[Route('/profile', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]
    public function index()
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();

        // Récupération de l'historique des actions récentes
        $recentActions = $this->userActionRepository->findRecentActionsForUser($user, 10);

        // Statistiques des actions
        $actionStats = $this->userActionRepository->getActionTypeStatsForUser($user);
        $totalActions = $this->userActionRepository->countActionsForUser($user);

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'recent_actions' => $recentActions,
            'action_stats' => $actionStats,
            'total_actions' => $totalActions,
        ]);
    }

    #[Route('/profile/edit/email', name: 'app_profile_email_edit')]
    #[IsGranted('ROLE_USER')]
    public function editEmail(Request $request): Response
    {
        $emailDTO = new EmailDTO();
        $form = $this->createForm(EmailFormType::class, $emailDTO);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /**
             * @var User $user
             */
            $user = $this->getUser();

            if ($this->passwordHasher->isPasswordValid($user, $emailDTO->password)) {
                $user->setEmail($emailDTO->email);
                $this->entityManager->flush();

                $this->addFlash('success', 'Votre adresse email a bien été modifiée.');

                return $this->redirectToRoute('app_profile');
            }

            $this->addFlash('error', 'Le mot de passe est incorrect.');
        }

        return $this->render('profile/edit_email.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/edit/password', name: 'app_profile_password_edit')]
    #[IsGranted('ROLE_USER')]
    public function editPassword(Request $request): Response
    {
        $passwordDTO = new PasswordDTO();
        $form = $this->createForm(PasswordFormType::class, $passwordDTO);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /**
             * @var User $user
             */
            $user = $this->getUser();

            if ($this->passwordHasher->isPasswordValid($user, $passwordDTO->password)) {
                $user->setPassword($this->passwordHasher->hashPassword($user, $passwordDTO->newPassword));
                $this->entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a bien été modifiée.');

                return $this->redirectToRoute('app_profile');
            }

            $this->addFlash('error', 'Le mot de passe est incorrect.');
        }

        return $this->render('profile/edit_password.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/profile/edit/avatar', name: 'app_profile_avatar_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function editAvatar(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(AvatarFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('avatar')->getData();

            $oldFilename = $user->getAvatarFilename();
            if ($oldFilename) {
                $oldPath = $this->projectDir . '/public/uploads/avatars/' . $oldFilename;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $newFilename = uniqid() . '.' . $avatarFile->guessExtension();
            $avatarFile->move($this->projectDir . '/public/uploads/avatars/', $newFilename);

            $user->setAvatarFilename($newFilename);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre image de profil a bien été modifiée.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/edit_avatar.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
        ]);
    }

    #[Route('/profile/delete/avatar', name: 'app_profile_avatar_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deleteAvatar(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('delete_avatar', $request->request->get('_token'))) {
            $this->addFlash('error', 'Action non autorisée.');

            return $this->redirectToRoute('app_profile');
        }

        $filename = $user->getAvatarFilename();
        if ($filename) {
            $path = $this->projectDir . '/public/uploads/avatars/' . $filename;
            if (file_exists($path)) {
                unlink($path);
            }
            $user->setAvatarFilename(null);
            $this->entityManager->flush();
        }

        $this->addFlash('success', 'Votre image de profil a été supprimée.');

        return $this->redirectToRoute('app_profile');
    }
}
