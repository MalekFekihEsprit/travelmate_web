<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de l\'événement',
                'attr'  => ['placeholder' => 'Ex: Atelier poterie'],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['rows' => 4, 'placeholder' => 'Décrivez l\'événement...'],
            ])
            ->add('date', DateType::class, [
                'label'  => 'Date',
                'widget' => 'single_text',
            ])
            ->add('heure', TimeType::class, [
                'label'        => 'Heure',
                'widget'       => 'single_text',
                'input'        => 'datetime',
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'attr'  => ['placeholder' => 'Ex: Sidi Bou Said'],
            ])
            ->add('nbPlaces', IntegerType::class, [
                'label' => 'Nombre de places',
                'attr'  => ['placeholder' => '20', 'min' => 1],
            ])
            ->add('lienGroupe', UrlType::class, [
                'label'      => 'Lien du groupe (optionnel)',
                'required'   => false,
                'default_protocol' => 'https',
                'attr'       => ['placeholder' => 'https://...'],
            ])
            ->add('imageFile', FileType::class, [
                'label'    => 'Image (JPG, PNG)',
                'mapped'   => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize'          => '5M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, WEBP)',
                    ]),
                ],
                'attr' => ['accept' => 'image/*'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
