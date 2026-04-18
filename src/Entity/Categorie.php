<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\CategorieRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ORM\Table(name: 'categories')]
#[UniqueEntity(
    fields: ['nom'],
    message: 'Une catégorie avec ce nom existe déjà. Veuillez choisir un nom différent.'
)]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false, unique: true)]
    #[Assert\NotBlank(message: 'Le nom de la catégorie est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le nom doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nom = null;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 15,
        minMessage: 'La description doit comporter au moins {{ limit }} caractères.'
    )]
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Le type doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le type ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $type = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'La saison est obligatoire.')]
    #[Assert\Choice(
        choices: ['printemps', 'été', 'automne', 'hiver', 'Toutes saisons', 'Printemps', 'Été', 'Automne', 'Hiver'],
        message: 'Veuillez choisir une saison valide.'
    )]
    private ?string $saison = null;

    public function getSaison(): ?string
    {
        return $this->saison;
    }

    public function setSaison(string $saison): self
    {
        $this->saison = $saison;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: "Le niveau d'intensité est obligatoire.")]
    #[Assert\Choice(
        choices: ['Faible', 'faible', 'Modéré', 'modéré', 'Élevé', 'élevé', 'Extrême', 'extrême', 'Moyen', 'moyen'],
        message: "Veuillez choisir un niveau d'intensité valide."
    )]
    private ?string $niveauintensite = null;

    public function getNiveauintensite(): ?string
    {
        return $this->niveauintensite;
    }

    public function setNiveauintensite(string $niveauintensite): self
    {
        $this->niveauintensite = $niveauintensite;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le public cible est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le public cible doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le public cible ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $publiccible = null;

    public function getPubliccible(): ?string
    {
        return $this->publiccible;
    }

    public function setPubliccible(string $publiccible): self
    {
        $this->publiccible = $publiccible;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Activite::class, mappedBy: 'categorie')]
    private Collection $activites;

    public function __construct()
    {
        $this->activites = new ArrayCollection();
    }

    /**
     * @return Collection<int, Activite>
     */
    public function getActivites(): Collection
    {
        if (!$this->activites instanceof Collection) {
            $this->activites = new ArrayCollection();
        }
        return $this->activites;
    }

    public function addActivite(Activite $activite): self
    {
        if (!$this->getActivites()->contains($activite)) {
            $this->getActivites()->add($activite);
        }
        return $this;
    }

    public function removeActivite(Activite $activite): self
    {
        $this->getActivites()->removeElement($activite);
        return $this;
    }
}
