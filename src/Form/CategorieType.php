<?php

namespace App\Form;

use App\Entity\Categorie;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategorieType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la catégorie',
                'attr'  => [
                    'placeholder' => 'Ex: Sports extrêmes',
                    'minlength'   => 3,
                    'maxlength'   => 100,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr'  => [
                    'rows'        => 4,
                    'placeholder' => 'Décrivez la catégorie (15 caractères minimum)...',
                    'minlength'   => 15,
                ],
            ])
            ->add('type', TextType::class, [
                'label' => 'Type',
                'attr'  => [
                    'placeholder' => 'Ex: Plein air, Culturel...',
                    'minlength'   => 3,
                    'maxlength'   => 50,
                ],
            ])
            ->add('saison', ChoiceType::class, [
                'label'       => 'Saison',
                'placeholder' => '-- Choisir une saison --',
                'choices'     => [
                    'Printemps'      => 'printemps',
                    'Été'            => 'été',
                    'Automne'        => 'automne',
                    'Hiver'          => 'hiver',
                    'Toutes saisons' => 'toutes saisons',
                ],
            ])
            ->add('niveauintensite', ChoiceType::class, [
                'label'       => 'Niveau d\'intensité',
                'placeholder' => '-- Choisir un niveau --',
                'choices'     => [
                    'Faible'  => 'faible',
                    'Modéré'  => 'modéré',
                    'Élevé'   => 'élevé',
                    'Extrême' => 'extrême',
                ],
            ])
            ->add('publiccible', TextType::class, [
                'label' => 'Public cible',
                'attr'  => [
                    'placeholder' => 'Ex: Adultes, Familles, Enfants...',
                    'minlength'   => 3,
                    'maxlength'   => 100,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Categorie::class,
        ]);
    }
}
