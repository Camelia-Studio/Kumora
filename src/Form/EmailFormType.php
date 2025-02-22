<?php

declare(strict_types=1);

namespace App\Form;

use App\DTO\EmailDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EmailFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Nouvelle adresse email',
                'attr' => [
                    'placeholder' => 'Nouvelle adresse email',
                    'autocomplete' => 'new-email',
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'attr' => [
                    'placeholder' => 'Mot de passe actuel',
                    'autocomplete' => 'new-password',
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Enregistrer',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EmailDTO::class,
            'attr' => [
                'autocomplete' => 'off',
            ]
        ]);
    }
}
