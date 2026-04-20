<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\Destination;
use App\Entity\Voyage;
use App\Repository\ActiviteRepository;
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
    /**
     * @return array<string, string[]>
     */
    private const DESTINATION_ALIASES = [
        'nice' => ['nice', 'france', 'french riviera', 'cote d azur', 'cote azur'],
        'rome' => ['rome', 'roma', 'italy', 'italie'],
        'milan' => ['milan', 'milano', 'italy', 'italie'],
        'tokyo' => ['tokyo', 'tokio', 'japan', 'japon'],
        'new york city' => ['new york', 'new york city', 'nyc', 'usa', 'united states', 'etats unis', 'americas', 'america'],
        'tunisia' => ['tunisia', 'tunisie', 'tunis', 'la marsa', 'marsa', 'gammarth', 'sidi bou said', 'jendouba', 'hammamet'],
        'hammamet' => ['hammamet', 'tunisia', 'tunisie', 'nabeul'],
        'istanbul' => ['istanbul', 'turkey', 'turquie', 'asia'],
    ];

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
                'choice_attr' => static function (Destination $destination): array {
                    $terms = self::buildDestinationTerms($destination);

                    return [
                        'data-destination-name' => (string) ($destination->getNomDestination() ?? $destination->getNom_destination() ?? ''),
                        'data-destination-country' => (string) ($destination->getPaysDestination() ?? $destination->getPays_destination() ?? ''),
                        'data-destination-region' => (string) ($destination->getRegionDestination() ?? $destination->getRegion_destination() ?? ''),
                        'data-destination-aliases' => implode('|', $terms),
                    ];
                },
                'attr' => [
                    'class' => 'travel-form__input',
                ],
            ])
            ->add('activites', EntityType::class, [
                'class' => Activite::class,
                'label' => 'Activites',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'query_builder' => static fn (ActiviteRepository $repository) => $repository
                    ->createQueryBuilder('a')
                    ->orderBy('a.nom', 'ASC'),
                'choice_label' => static function (Activite $activite): string {
                    $nom = $activite->getNom() ?? 'Activite';
                    $lieu = $activite->getLieu();

                    return $lieu ? sprintf('%s - %s', $nom, $lieu) : $nom;
                },
                'choice_attr' => static function (Activite $activite): array {
                    $haystack = implode(' ', array_filter([
                        $activite->getNom(),
                        $activite->getLieu(),
                        $activite->getDescription(),
                    ]));

                    return [
                        'class' => 'voyage-activity-option__input',
                        'data-activity-haystack' => $haystack,
                        'data-activity-lieu' => (string) ($activite->getLieu() ?? ''),
                        'data-activity-duree' => $activite->getDuree() !== null ? (string) $activite->getDuree() : '',
                    ];
                },
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

    /**
     * @return string[]
     */
    private static function buildDestinationTerms(Destination $destination): array
    {
        $values = array_filter([
            $destination->getNomDestination() ?? $destination->getNom_destination(),
            $destination->getPaysDestination() ?? $destination->getPays_destination(),
            $destination->getRegionDestination() ?? $destination->getRegion_destination(),
        ]);

        $terms = [];

        foreach ($values as $value) {
            $normalized = self::normalize((string) $value);

            if ($normalized === '') {
                continue;
            }

            $terms[] = $normalized;

            if (isset(self::DESTINATION_ALIASES[$normalized])) {
                array_push($terms, ...self::DESTINATION_ALIASES[$normalized]);
            }
        }

        return array_values(array_unique(array_filter($terms)));
    }

    private static function normalize(string $value): string
    {
        $normalized = $value;

        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII;', $value);

            if (is_string($transliterated)) {
                $normalized = $transliterated;
            }
        }

        $normalized = mb_strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized);

        return trim((string) $normalized);
    }
}