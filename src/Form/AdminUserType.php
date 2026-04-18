<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire.'),
                    new Length(max: 255),
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [
                    new NotBlank(message: 'Le prénom est obligatoire.'),
                    new Length(max: 255),
                ],
            ])
            ->add('date_naissance', DateType::class, [
                'label' => 'Date de naissance',
                'widget' => 'single_text',
                'html5' => true,
                'required' => true,
                'input' => 'datetime',  
                'empty_data' => null,
                'constraints' => [
                    new NotBlank(message: 'La date de naissance est obligatoire.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new NotBlank(message: 'L’email est obligatoire.'),
                    new Email(message: 'Veuillez saisir une adresse email valide.'),
                    new Length(max: 255),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'required' => false,
                'constraints' => [
                    new Length(max: 255),
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Utilisateur' => 'USER',
                    'Administrateur' => 'ADMIN',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le rôle est obligatoire.'),
                ],
            ])
            ->add('photoUrl', TextType::class, [
                'label' => 'URL de la photo',
                'required' => false,
                'constraints' => [
                    new Length(max: 255),
                    new Url(message: 'Veuillez saisir une URL valide.'),
                ],
                'attr' => [
                    'placeholder' => 'https://...',
                ],
            ])
            ->add('photoFile', FileType::class, [
                'label' => 'Fichier photo de profil',
                'required' => false,
                'mapped' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                            'image/gif'
                        ],
                        'mimeTypesMessage' => 'Veuillez uploader une image valide (JPEG, PNG, WEBP ou GIF)',
                        'maxSizeMessage' => 'Le fichier est trop volumineux (max 5Mo)'
                    ])
                ],
                'attr' => [
                    'accept' => 'image/jpeg,image/png,image/webp,image/gif'
                ]
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $isEdit ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe',
                'mapped' => false,
                'required' => !$isEdit,
                'empty_data' => '',
                'constraints' => [
                    new Callback(function($value, ExecutionContextInterface $context) use ($isEdit) {
                        // Skip validation if empty in edit mode
                        if ($isEdit && empty($value)) {
                            return;
                        }
                        
                        // Validate password
                        if (empty($value)) {
                            $context->buildViolation('Le mot de passe est obligatoire.')
                                ->addViolation();
                            return;
                        }
                        
                        if (strlen($value) < 8) {
                            $context->buildViolation('Le mot de passe doit contenir au moins {{ limit }} caractères.')
                                ->setParameter('{{ limit }}', '8')
                                ->addViolation();
                        }
                        
                        if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d).+$/', $value)) {
                            $context->buildViolation('Le mot de passe doit contenir au moins une lettre et un chiffre.')
                                ->addViolation();
                        }
                    }),
                ],
            ])
            ->add('isVerified', ChoiceType::class, [
                'label' => 'Statut de vérification',
                'choices' => [
                    'Vérifié' => true,
                    'Non vérifié' => false,
                ],
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}