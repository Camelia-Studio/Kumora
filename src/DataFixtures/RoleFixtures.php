<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AccessGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class RoleFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $conseilAdmin = (new AccessGroup())
            ->setName('Conseil d\'administration')
            ->setPosition(1)
        ;

        $visiteur = (new AccessGroup())
            ->setName('Visiteur')
            ->setPosition(100)
        ;

        $manager->persist($conseilAdmin);
        $manager->persist($visiteur);
        $manager->flush();
    }
}
