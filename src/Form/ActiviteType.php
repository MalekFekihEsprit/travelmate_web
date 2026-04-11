<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\Categorie;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'activité',
                'attr'  => [
                    'placeholder' => '⚠ Obligatoire — entre 3 et 100 caractères',
                    'minlength'   => 3,
                    'maxlength'   => 100,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr'  => [
                    'rows'        => 4,
                    'placeholder' => '⚠ Obligatoire — minimum 15 caractères',
                    'minlength'   => 15,
                ],
            ])
            ->add('budget', IntegerType::class, [
                'label' => 'Budget (DT)',
                'attr'  => [
                    'placeholder' => '⚠ Obligatoire — nombre positif, max 100 000 DT',
                    'min'         => 1,
                    'max'         => 100000,
                ],
            ])
            ->add('niveaudifficulte', ChoiceType::class, [
                'label'       => 'Niveau de difficulté',
                'placeholder' => '⚠ Obligatoire — choisissez un niveau',
                'choices'     => [
                    'Facile'        => 'facile',
                    'Intermédiaire' => 'intermediaire',
                    'Difficile'     => 'difficile',
                    'Expert'        => 'expert',
                ],
            ])
            ->add('lieu', TextType::class, [
                'label'    => 'Lieu',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Optionnel — max 150 caractères',
                    'maxlength'   => 150,
                ],
            ])
            ->add('latitude', NumberType::class, [
                'label'       => 'Latitude (carte)',
                'required'    => false,
                'scale'       => 7,
                'html5'       => true,
                'attr'        => [
                    'placeholder' => 'Ex. 36.8065 — optionnel, filtre par distance',
                    'step'        => 'any',
                ],
                'help'        => 'Coordonnée GPS du lieu (WGS84). Améliore le filtre « destination » sur le site.',
            ])
            ->add('longitude', NumberType::class, [
                'label'       => 'Longitude (carte)',
                'required'    => false,
                'scale'       => 7,
                'html5'       => true,
                'attr'        => [
                    'placeholder' => 'Ex. 10.1815',
                    'step'        => 'any',
                ],
            ])
            ->add('agemin', IntegerType::class, [
                'label' => 'Âge minimum',
                'attr'  => [
                    'placeholder' => '⚠ Obligatoire — entre 0 et 120 ans',
                    'min'         => 0,
                    'max'         => 120,
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label'       => 'Statut',
                'placeholder' => '⚠ Obligatoire — choisissez un statut',
                'choices'     => [
                    'Active'   => 'active',
                    'Inactive' => 'inactive',
                    'Archivée' => 'archivee',
                ],
            ])
            ->add('duree', IntegerType::class, [
                'label' => 'Durée (heures)',
                'attr'  => [
                    'placeholder' => '⚠ Obligatoire — entre 1 et 720 heures',
                    'min'         => 1,
                    'max'         => 720,
                ],
            ])
            ->add('categorie', EntityType::class, [
                'class'        => Categorie::class,
                'choice_label' => 'nom',
                'label'        => 'Catégorie',
                'placeholder'  => '⚠ Obligatoire — choisissez une catégorie',
            ])
            ->add('imageFile', FileType::class, [
                'label'    => 'Image (JPG, PNG, WEBP)',
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
            'data_class' => Activite::class,
        ]);
    }
}
