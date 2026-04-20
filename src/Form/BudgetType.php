<?php

namespace App\Form;

use App\Entity\Budget;
use App\Entity\Voyage;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BudgetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('libelleBudget', TextType::class, [
                'label' => 'Libellé',
                'required' => true,
                'attr' => ['maxlength' => 20]
            ])
            ->add('montantTotal', NumberType::class, [
                'label' => 'Montant total',
                'required' => true,
                'scale' => 2,
                'attr' => ['min' => 0.01, 'step' => 0.01]
            ])
            ->add('deviseBudget', ChoiceType::class, [
                'label' => 'Devise',
                'required' => true,
                'choices' => [
                    'Dinar tunisien (TND)' => 'TND',
                    'Euro (EUR)' => 'EUR',
                    'Dollar américain (USD)' => 'USD',
                    'Livre sterling (GBP)' => 'GBP',
                    'Dirham marocain (MAD)' => 'MAD',
                    'Dinar algérien (DZD)' => 'DZD',
                    'Franc suisse (CHF)' => 'CHF',
                    'Yen japonais (JPY)' => 'JPY',
                    'Yuan chinois (CNY)' => 'CNY',
                    'Dollar canadien (CAD)' => 'CAD',
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('descriptionBudget', TextareaType::class, [
                'label' => 'Description',
                'required' => true,
                'attr' => ['maxlength' => 100, 'rows' => 4]
            ])
            ->add('statutBudget', ChoiceType::class, [
                'label' => 'Statut',
                'required' => false,
                'choices' => [
                    'Actif' => 'actif',
                    'Suspendu' => 'suspendu',
                    'Clôturé' => 'cloture',
                    'Dépassé' => 'depasse',
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('voyage', EntityType::class, [
                'class' => Voyage::class,
                'choice_label' => 'titreVoyage',
                'label' => 'Voyage associé',
                'required' => true,
                'placeholder' => '-- Choisir un voyage --',
                'attr' => ['class' => 'form-select']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Budget::class,
        ]);
    }
}