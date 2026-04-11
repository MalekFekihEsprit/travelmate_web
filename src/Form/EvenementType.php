<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
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
                'attr'  => [
                    'placeholder' => '⚠ Obligatoire — entre 5 et 255 caractères',
                    'minlength'   => 5,
                    'maxlength'   => 255,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => [
                    'rows'        => 4,
                    'placeholder' => 'Optionnel — si renseignée, minimum 15 caractères',
                    'minlength'   => 15,
                ],
            ])
            ->add('date', DateType::class, [
                'label'  => 'Date',
                'widget' => 'single_text',
                'attr'   => [
                    'placeholder' => '⚠ Obligatoire — doit être une date future',
                    'min'         => (new \DateTime())->format('Y-m-d'),
                ],
            ])
            ->add('heure', TimeType::class, [
                'label'  => 'Heure',
                'widget' => 'single_text',
                'input'  => 'datetime',
                'attr'   => [
                    'placeholder' => '⚠ Obligatoire',
                ],
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'attr'  => [
                    'placeholder' => '⚠ Obligatoire — entre 3 et 255 caractères',
                    'minlength'   => 3,
                    'maxlength'   => 255,
                ],
            ])
            ->add('latitude', NumberType::class, [
                'label'      => 'Latitude (carte)',
                'required'   => false,
                'scale'      => 7,
                'html5'      => true,
                'empty_data' => null,
                'attr'       => [
                    'placeholder' => 'Optionnel — position exacte sur la carte',
                    'step'        => 'any',
                ],
                'help'       => 'Coordonnées GPS (WGS84). Permet d\'afficher la carte sur la fiche événement.',
            ])
            ->add('longitude', NumberType::class, [
                'label'      => 'Longitude (carte)',
                'required'   => false,
                'scale'      => 7,
                'html5'      => true,
                'empty_data' => null,
                'attr'       => [
                    'placeholder' => 'Ex. 10.1815',
                    'step'        => 'any',
                ],
            ])
            ->add('nbPlaces', IntegerType::class, [
                'label' => 'Nombre de places',
                'attr'  => [
                    'placeholder' => '⚠ Obligatoire — entre 1 et 10 000',
                    'min'         => 1,
                    'max'         => 10000,
                ],
            ])
            ->add('lienGroupe', UrlType::class, [
                'label'            => 'Lien du groupe (optionnel)',
                'required'         => false,
                'default_protocol' => 'https',
                'attr'             => [
                    'placeholder' => 'Optionnel — URL valide commençant par https://',
                    'maxlength'   => 500,
                ],
            ])
            ->add('imageFile', FileType::class, [
                'label'    => 'Image (JPG, PNG, WEBP)',
                'mapped'   => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize'          => '5M',
                        'mimeTypes'        => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPG, PNG, WEBP) — max 5 Mo',
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
