<?php

namespace App\Form;

use App\Entity\Destination;
use App\Entity\Hebergement;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class HebergementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_hebergement', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom est requis']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 120,
                        'minMessage' => 'Le nom doit avoir au moins 2 caracteres',
                        'maxMessage' => 'Le nom ne doit pas depasser 120 caracteres',
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[a-zA-ZÀ-ÿ\s-]+$/',
                        'message' => 'Le nom ne doit contenir que des lettres',
                    ]),
                ],
            ])
            ->add('type_hebergement', ChoiceType::class, [
                'required' => false,
                'placeholder' => 'Choisir un type',
                'choices' => [
                    'Hotel' => 'Hotel',
                    'Resort' => 'Resort',
                    'Maison d\'hôtes' => 'Maison d\'hôtes',
                    'Appartement' => 'Appartement',
                    'Villa' => 'Villa',
                    'Hostel' => 'Hostel',
                    'Bungalow' => 'Bungalow',
                    'Auberge' => 'Auberge',
                ],
                'constraints' => [
                    new Assert\Choice([
                        'choices' => ['Hotel', 'Resort', "Maison d'hôtes", 'Appartement', 'Villa', 'Hostel', 'Bungalow', 'Auberge'],
                        'multiple' => false,
                        'message' => 'Veuillez choisir un type d\'hebergement valide',
                    ]),
                ],
            ])
            ->add('prixNuit_hebergement', NumberType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\PositiveOrZero(['message' => 'Le prix doit etre positif']),
                ],
            ])
            ->add('adresse_hebergement', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'L\'adresse ne doit pas depasser 255 caracteres',
                    ]),
                ],
            ])
            ->add('note_hebergement', NumberType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\Range([
                        'min' => 0,
                        'max' => 5,
                        'notInRangeMessage' => 'La note doit etre entre 0 et 5',
                    ]),
                ],
            ])
            ->add('latitude_hebergement', NumberType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\Range([
                        'min' => -90,
                        'max' => 90,
                        'notInRangeMessage' => 'La latitude doit etre entre -90 et 90',
                    ]),
                ],
            ])
            ->add('longitude_hebergement', NumberType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\Range([
                        'min' => -180,
                        'max' => 180,
                        'notInRangeMessage' => 'La longitude doit etre entre -180 et 180',
                    ]),
                ],
            ])
            ->add('destination', EntityType::class, [
                'class' => Destination::class,
                'choice_label' => 'nomDestination',
                'required' => false,
                'placeholder' => 'Choisir une destination',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Hebergement::class,
        ]);
    }
}
