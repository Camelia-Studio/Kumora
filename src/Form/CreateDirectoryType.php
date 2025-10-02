<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\AccessGroup;
use App\Repository\AccessGroupRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CreateDirectoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nom du dossier',
            ])
        ;

        // Ajouter les champs de permissions uniquement pour les dossiers racine
        if ($options['is_root_directory']) {
            $builder
                ->add('ownerRole', EntityType::class, [
                    'class' => AccessGroup::class,
                    'label' => 'Groupe d\'accès minimum',
                    'query_builder' => static fn (AccessGroupRepository $repository) => $repository->createQueryBuilder('a')
                        ->orderBy('a.position', 'ASC'),
                    'choice_label' => 'name',
                    'mapped' => false,
                ])
                ->add('typeDossier', ChoiceType::class, [
                    'label' => 'Type de dossier',
                    'mapped' => false,
                    'choices' => [
                        'Dossier de partage' => 'shared',
                        'Dossier privé' => 'private',
                    ],
                    'data' => 'private',
                ])
                ->add('parentDirectoryPermissions', CollectionType::class, [
                    'entry_type' => ParentDirectoryPermissionType::class,
                    'entry_options' => [
                        'label' => false,
                    ],
                    'allow_add' => true,
                    'allow_delete' => true,
                    'mapped' => false,
                    'label' => 'Permissions des groupes d\'accès inférieurs',
                ])
            ;
        }

        $builder->add('submit', SubmitType::class, [
            'label' => 'Créer',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'is_root_directory' => false,
        ]);
        $resolver->setAllowedTypes('is_root_directory', 'bool');
    }
}
