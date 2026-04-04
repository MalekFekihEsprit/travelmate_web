<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DeleteAccountFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('password', PasswordType::class, [
                'label' => 'Confirmez votre mot de passe',
                'mapped' => false,
                'attr' => [
                    'placeholder' => 'Votre mot de passe',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le mot de passe est obligatoire.'),
                ],
            ])
            ->add('confirm', CheckboxType::class, [
                'label' => 'Je confirme que je souhaite supprimer définitivement mon compte',
                'mapped' => false,
                'constraints' => [
                    new IsTrue(message: 'Vous devez confirmer la suppression du compte.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}