<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\ParentDirectory;
use App\Entity\ParentDirectoryPermission;
use App\Entity\User;
use App\Enum\RoleEnum;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParentDirectoryPermissionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('role', EnumType::class, [
                'class' => RoleEnum::class,
                'label' => 'Rôle',
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
