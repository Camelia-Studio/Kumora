<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\AccessGroup;
use App\Entity\User;
use App\Repository\AccessGroupRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullname', null, [
                'label' => 'Nom complet',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
            ])
            ->add('accessGroup', EntityType::class, [
                'label' => 'Groupe d\'accès',
                'query_builder' => static fn (AccessGroupRepository $repository) => $repository->createQueryBuilder('a')
                    ->orderBy('a.position', 'ASC'),
                'choice_label' => 'name',
                'class' => AccessGroup::class,
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Utilisateur' => 'ROLE_USER',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'mapped' => false,
            ])
        ;

        // Si l'utilisateur est nouveau, on ajoute le champ de mot de passe
        if (!$options['data']->getId()) {
            $builder->add('plainPassword', PasswordType::class, [
                'label' => 'Mot de passe',
                'required' => true,
                'mapped' => false,
            ]);
        } else {
            // On set le rôle actuel de l'utilisateur
            $builder->get('role')->setData($options['data']->getRoles()[0]);
        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'Enregistrer',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
