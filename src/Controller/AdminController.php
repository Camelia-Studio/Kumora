<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\UserAdminType;
use App\Repository\UserActionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordEncoder,
        private readonly UserRepository $userRepository,
        private readonly UserActionRepository $userActionRepository,
        private readonly ChartBuilderInterface $chartBuilder
    ) {
    }

    #[Route('/admin/', name: 'app_admin_index')]
    public function index(): Response
    {
        // Récupération des statistiques pour les graphiques
        $globalActionStats = $this->userActionRepository->getGlobalActionTypeStats();
        $actionsByDay = $this->userActionRepository->getActionsByDay(30);
        $mostActiveUsers = $this->userActionRepository->getMostActiveUsers(5);
        $totalActions = $this->userActionRepository->getTotalActions();
        $recentActions = $this->userActionRepository->getRecentGlobalActions(10);
        $totalUsers = $this->userRepository->count([]);

        // Création du graphique en camembert pour les types d'actions
        $actionTypesChart = $this->chartBuilder->createChart(Chart::TYPE_PIE);
        $actionTypesChart->setData([
            'labels' => array_keys($globalActionStats),
            'datasets' => [
                [
                    'label' => 'Actions par type',
                    'backgroundColor' => [
                        'rgb(34, 197, 94)',   // green-500
                        'rgb(59, 130, 246)',  // blue-500
                        'rgb(245, 158, 11)',  // amber-500
                        'rgb(239, 68, 68)',   // red-500
                        'rgb(168, 85, 247)',  // purple-500
                        'rgb(156, 163, 175)', // gray-400
                    ],
                    'data' => array_values($globalActionStats),
                ],
            ],
        ]);
        $actionTypesChart->setOptions([
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 20,
                        'usePointStyle' => true,
                    ],
                ],
            ],
        ]);

        // Création du graphique en ligne pour l'activité quotidienne
        $dailyActivityChart = $this->chartBuilder->createChart(Chart::TYPE_LINE);
        $dailyActivityChart->setData([
            'labels' => array_keys($actionsByDay),
            'datasets' => [
                [
                    'label' => 'Actions par jour',
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'data' => array_values($actionsByDay),
                    'fill' => true,
                ],
            ],
        ]);
        $dailyActivityChart->setOptions([
            'responsive' => true,
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ]);

        // Création du graphique en barres pour les utilisateurs les plus actifs
        $activeUsersChart = $this->chartBuilder->createChart(Chart::TYPE_BAR);
        $activeUsersChart->setData([
            'labels' => array_map(static fn ($user) => $user['fullname'], $mostActiveUsers),
            'datasets' => [
                [
                    'label' => 'Nombre d\'actions',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'data' => array_map(static fn ($user) => $user['actionCount'], $mostActiveUsers),
                ],
            ],
        ]);
        $activeUsersChart->setOptions([
            'responsive' => true,
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ]);

        return $this->render('admin/index.html.twig', [
            'action_types_chart' => $actionTypesChart,
            'daily_activity_chart' => $dailyActivityChart,
            'active_users_chart' => $activeUsersChart,
            'total_actions' => $totalActions,
            'recent_actions' => $recentActions,
            'total_users' => $totalUsers,
        ]);
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
