<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\EmailDTO;
use App\DTO\PasswordDTO;
use App\Entity\User;
use App\Form\EmailFormType;
use App\Form\PasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    )
    {
    }

    #[Route('/profile', name: 'app_profile')]
    #[IsGranted('ROLE_USER')]
    public function index()
    {
        /**
         * @var User $user
         */
        $user = $this->getUser();

        return $this->render('profile/index.html.twig', [
            'user' => $user,
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
}
