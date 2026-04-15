<?php

namespace App\Form;

use App\Entity\Destination;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DestinationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_destination', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom est requis']),
                    new Assert\Length(['min' => 2, 'minMessage' => 'Le nom doit avoir au moins 2 caractères']),
                    new Assert\Regex(['pattern' => '/^[a-zA-ZÀ-ÿ\s-]+$/', 'message' => 'Le nom ne doit contenir que des lettres']),
                ]
            ])
            ->add('pays_destination', TextType::class, [
                'label' => 'Pays',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le pays est requis']),
                    new Assert\Regex(['pattern' => '/^[a-zA-ZÀ-ÿ\s-]+$/', 'message' => 'Le pays ne doit contenir que des lettres']),
                ]
            ])
            ->add('region_destination', TextType::class, [
                'label' => 'Ville',
                'required' => false,
                'constraints' => [
                    new Assert\Regex(['pattern' => '/^[a-zA-ZÀ-ÿ\s-]*$/', 'message' => 'La ville ne doit contenir que des lettres']),
                ]
            ])
            ->add('description_destination', TextareaType::class, [
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La description est requise']),
                    new Assert\Length(['min' => 10, 'minMessage' => 'La description doit avoir au moins 10 caractères']),
                ]
            ])
            ->add('climat_destination', ChoiceType::class, [
                'choices' => [
                    'Tropical' => 'Tropical',
                    'Méditerranéen' => 'Méditerranéen',
                    'Tempéré' => 'Tempéré',
                    'Désertique' => 'Désertique',
                    'Continental' => 'Continental',
                    'Montagnard' => 'Montagnard',
                    'Équatorial' => 'Équatorial',
                    'Polaire' => 'Polaire',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le climat est requis']),
                ]
            ])
            ->add('saison_destination', ChoiceType::class, [
                'choices' => [
                    'Printemps' => 'Printemps',
                    'Été' => 'Été',
                    'Automne' => 'Automne',
                    'Hiver' => 'Hiver',
                    'Toute l\'année' => 'Toute l\'année',
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La saison idéale est requise']),
                ]
            ])
            ->add('video_url', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\Url(['message' => 'Veuillez entrer une URL valide']),
                ]
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
