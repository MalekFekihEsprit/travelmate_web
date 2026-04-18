<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class VerifyEmailCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('code', TextType::class, [
            'label' => 'Code de vérification',
            'attr' => [
                'placeholder' => 'Ex: 123456',
                'maxlength' => 6,
            ],
            'constraints' => [
                new NotBlank(message: 'Le code est obligatoire.'),
                new Length(
                    min: 6,
                    max: 6,
                    exactMessage: 'Le code doit contenir exactement {{ limit }} caractères.'
                ),
            ],
        ]);
    }
}