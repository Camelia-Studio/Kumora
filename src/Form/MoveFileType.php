<?php

declare(strict_types=1);

namespace App\Form;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MoveFileType extends AbstractType
{
    /**
     * @throws FilesystemException
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $autocompleteUrl = $options['autocomplete_url'] ?? '/kumora/autocomplete/path/file';

        $builder
            ->add('path', TextType::class, [
                'label' => 'Nouveau chemin',
                'attr' => [
                    'placeholder' => 'Saisissez le nouveau chemin',
                ],
                'autocomplete' => true,
                'autocomplete_url' => $autocompleteUrl,
                'tom_select_options' => [
                    'create' => true,
                    'createOnBlur' => true,
                    'maxItems' => 1,
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Déplacer',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'autocomplete_url' => null,
        ]);
        $resolver->setAllowedTypes('autocomplete_url', ['null', 'string']);
    }
}
