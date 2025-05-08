<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AccessGroup;
use App\Form\AccessGroupType;
use App\Repository\AccessGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AccessGroupController extends AbstractController
{
    #[Route('/admin/access-group', name: 'app_admin_access_group_index', methods: ['GET'])]
    public function index(AccessGroupRepository $accessGroupRepository): Response
    {
        return $this->render('access_group/index.html.twig', [
            'access_groups' => $accessGroupRepository->findBy([], ['position' => 'ASC']),
        ]);
    }
    #[Route('/admin/access-group/new', name: 'app_access_group_new', methods: ['GET', 'POST'])]
    #[Route('/admin/access-group/{id}/edit', name: 'app_access_group_edit', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ?AccessGroup $accessGroup, AccessGroupRepository $accessGroupRepository): Response
    {
        if (!$accessGroup instanceof AccessGroup) {
            $accessGroup = new AccessGroup();
            $count = $accessGroupRepository->count();
            $accessGroup->setPosition($count + 1);
        }

        $form = $this->createForm(AccessGroupType::class, $accessGroup);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message = "Le groupe d'accès a bien été créé !";

            if (null !== $accessGroup->getId()) {
                $message = "Le groupe d'accès a bien été modifié !";
            }

            $entityManager->persist($accessGroup);
            $entityManager->flush();

            $this->addFlash('success', $message);

            return $this->redirectToRoute('app_admin_access_group_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('access_group/new.html.twig', [
            'access_group' => $accessGroup,
            'form' => $form,
        ]);
    }
    #[Route('/admin/access-group/{id}', name: 'app_access_group_delete', methods: ['GET'])]
    public function delete(AccessGroup $accessGroup, EntityManagerInterface $entityManager, AccessGroupRepository $accessGroupRepository): Response
    {
        // On vérifie si le groupe d'accès n'est pas utilisé par un utilisateur
        // Si c'est le cas, on le retire des utilisateurs (On met celui avec la première position inférieure)
        $users = $accessGroup->getUsers();

        if (count($users) > 0) {
            $access = $accessGroupRepository->getInferiorRole($accessGroup);

            foreach ($users as $user) {
                $accessGroup->removeUser($user);
                $user->setAccessGroup($access);

                $entityManager->persist($user);
            }
        }

        $parentDirectories = $accessGroup->getParentDirectories();
        if (count($parentDirectories) > 0) {
            $access = $accessGroupRepository->getSuperiorRole($accessGroup);
            foreach ($parentDirectories as $parentDirectory) {
                $accessGroup->removeParentDirectory($parentDirectory);
                $parentDirectory->setAccessGroup($access);

                $entityManager->persist($parentDirectory);
            }
        }
        $parentDirectoryPermissions = $accessGroup->getParentDirectoryPermissions();

        if (count($parentDirectoryPermissions) > 0) {
            $access = $accessGroupRepository->getSuperiorRole($accessGroup);
            foreach ($parentDirectoryPermissions as $parentDirectoryPermission) {
                $accessGroup->removeParentDirectoryPermission($parentDirectoryPermission);
                $parentDirectoryPermission->setAccessGroup($access);

                $entityManager->persist($parentDirectoryPermission);
            }
        }

        $entityManager->flush();


        $entityManager->remove($accessGroup);
        $entityManager->flush();

        return $this->redirectToRoute('app_admin_access_group_index', [], Response::HTTP_SEE_OTHER);
    }
}
