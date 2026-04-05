<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\Categorie;
use App\Entity\Voyage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom')
            ->add('description')
            ->add('budget')
            ->add('niveaudifficulte')
            ->add('lieu')
            ->add('agemin')
            ->add('statut')
            ->add('duree')
            ->add('imagePath')
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'choice_label' => 'id',
            ])
            ->add('voyages', EntityType::class, [
                'class' => Voyage::class,
                'choice_label' => 'id',
                'multiple' => true,
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
