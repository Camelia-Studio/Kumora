<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\RoleEnum;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Permet de créer un utilisateur',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $io->ask('Email de l\'utilisateur');
        $password = $io->askHidden('Mot de passe de l\'utilisateur');
        $isAdmin = $io->confirm('Est-ce un administrateur ?');
        $folderRole = $io->choice('Rôle du dossier', array_map(static fn ($role) => $role->value, RoleEnum::cases()), RoleEnum::VISITEUR->value);

        try {
            $user = $this->userRepository->findOneBy(['email' => $email]);
            if (null !== $user) {
                $io->error('Un utilisateur existe déjà avec cet email');
                return Command::FAILURE;
            }
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setRoles($isAdmin ? ['ROLE_ADMIN'] : ['ROLE_USER']);
            $user->setFolderRole(RoleEnum::from($folderRole));

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $io->success('Utilisateur créé avec succès');
        } catch (\Exception $e) {
            $io->error('Une erreur est survenue lors de la création de l\'utilisateur : ' . $e->getMessage());
        }

        return Command::SUCCESS;
    }
}
