<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\UserAdminType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordEncoder,
        private readonly UserRepository $userRepository
    ) {
    }

    #[Route('/admin/', name: 'app_admin_index')]
    public function index(): Response
    {
        return $this->render('admin/index.html.twig');
    }

    #[Route('/admin/users', name: 'app_admin_user_index')]
    public function indexUsers(): Response
    {
        $users = $this->userRepository->findAll();
        return $this->render('admin/user_index.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/users/create', name: 'app_admin_user_create')]
    #[Route('/admin/users/edit/{user}', name: 'app_admin_user_edit')]
    public function editUsers(#[MapEntity(id: 'user')] ?User $user, Request $request): Response
    {
        $isNew = false;
        if (!$user instanceof \App\Entity\User) {
            $user = new User();
            $isNew = true;
        }

        $form = $this->createForm(UserAdminType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $role = $form->get('role')->getData();
            $user->setRoles([$role]);

            if ($form->has('plainPassword')) {
                $plainPassword = $form->get('plainPassword')->getData();
                $user->setPassword($this->passwordEncoder->hashPassword($user, $plainPassword));
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'L\'utilisateur a bien été enregistré !');
            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/user_edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'isNew' => $isNew,
        ]);
    }

    #[Route('/admin/users/delete/{user}', name: 'app_admin_user_delete')]
    public function deleteUser(#[MapEntity(id: 'user')] User $user): Response
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'L\'utilisateur a bien été supprimé !');
        return $this->redirectToRoute('app_admin_user_index');
    }
}
