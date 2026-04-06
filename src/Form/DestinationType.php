<?php

namespace App\Form;

use App\Entity\Destination;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DestinationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_destination')
            ->add('pays_destination')
            ->add('region_destination')
            ->add('description_destination')
            ->add('climat_destination')
            ->add('saison_destination')
            ->add('latitude_destination')
            ->add('longitude_destination')
            ->add('score_destination')
            ->add('currency_destination')
            ->add('flag_destination')
            ->add('languages_destination')
            ->add('video_url')
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Destination::class,
        ]);
    }
}
