<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\AccessGroup;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AccessGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', null, [
                'label' => 'Nom',
            ])
            ->add('position', null, [
                'label' => 'Position',
            ])
            ->add('image', UrlType::class, [
                'label' => 'Lien vers l\'image',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AccessGroup::class,
        ]);
    }
}
