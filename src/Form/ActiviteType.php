<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\Categorie;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
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
                'attr'  => ['placeholder' => 'Ex: Randonnée en montagne'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr'  => ['rows' => 4, 'placeholder' => 'Décrivez l\'activité...'],
            ])
            ->add('budget', IntegerType::class, [
                'label' => 'Budget (DT)',
                'attr'  => ['placeholder' => '50'],
            ])
            ->add('niveaudifficulte', ChoiceType::class, [
                'label'   => 'Niveau de difficulté',
                'choices' => [
                    'Facile'       => 'facile',
                    'Intermédiaire' => 'intermediaire',
                    'Difficile'    => 'difficile',
                    'Expert'       => 'expert',
                ],
            ])
            ->add('lieu', TextType::class, [
                'label'    => 'Lieu',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex: Tunis, Hammamet...'],
            ])
            ->add('agemin', IntegerType::class, [
                'label' => 'Âge minimum',
                'attr'  => ['placeholder' => '18'],
            ])
            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => [
                    'Active'   => 'active',
                    'Inactive' => 'inactive',
                    'Archivée' => 'archivee',
                ],
            ])
            ->add('duree', IntegerType::class, [
                'label' => 'Durée (heures)',
                'attr'  => ['placeholder' => '2'],
            ])
            ->add('categorie', EntityType::class, [
                'class'        => Categorie::class,
                'choice_label' => 'nom',
                'label'        => 'Catégorie',
                'placeholder'  => '-- Choisir une catégorie --',
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
            'data_class' => Activite::class,
        ]);
    }
}
