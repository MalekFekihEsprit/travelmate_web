<?php

namespace App\Form;

use App\Entity\Destination;
use App\Entity\Voyage;
use App\Repository\DestinationRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VoyageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre_voyage', TextType::class, [
                'label' => 'Titre du voyage',
                'attr' => [
                    'class' => 'travel-form__input',
                    'placeholder' => 'Ex. Escapade a Rome',
                    'maxlength' => 120,
                ],
            ])
            ->add('destination', EntityType::class, [
                'class' => Destination::class,
                'label' => 'Destination',
                'placeholder' => 'Choisir une destination',
                'query_builder' => static fn (DestinationRepository $repository) => $repository
                    ->createQueryBuilder('d')
                    ->orderBy('d.nom_destination', 'ASC'),
                'choice_label' => static function (Destination $destination): string {
                    $nom = $destination->getNomDestination() ?? $destination->getNom_destination() ?? 'Destination';
                    $pays = $destination->getPaysDestination() ?? $destination->getPays_destination();

                    return $pays ? sprintf('%s - %s', $nom, $pays) : $nom;
                },
                'attr' => [
                    'class' => 'travel-form__input',
                ],
            ])
            ->add('date_debut', DateType::class, [
                'label' => 'Date de debut',
                'widget' => 'single_text',
                'input' => 'datetime',
                'attr' => [
                    'class' => 'travel-form__input',
                ],
            ])
            ->add('date_fin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'input' => 'datetime',
                'attr' => [
                    'class' => 'travel-form__input',
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'placeholder' => 'Choisir un statut',
                'choices' => array_combine(Voyage::getAvailableStatuts(), Voyage::getAvailableStatuts()),
                'attr' => [
                    'class' => 'travel-form__input',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Voyage::class,
        ]);
    }
}