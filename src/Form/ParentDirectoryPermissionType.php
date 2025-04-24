<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\AccessGroup;
use App\Entity\ParentDirectoryPermission;
use App\Repository\AccessGroupRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParentDirectoryPermissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('role', EntityType::class, [
                'label' => 'Groupe d\'accès',
                'class' => AccessGroup::class,
                'query_builder' => static fn (AccessGroupRepository $repository) => $repository->createQueryBuilder('a')
                    ->orderBy('a.position', 'ASC'),
                'choice_label' => 'name',
            ])
            ->add('read', null, [
                'label' => 'Lecture',
            ])
            ->add('write', null, [
                'label' => 'Écriture',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ParentDirectoryPermission::class,
        ]);
    }
}
