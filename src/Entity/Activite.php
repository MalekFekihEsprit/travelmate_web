<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\ActiviteRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActiviteRepository::class)]
#[ORM\Table(name: 'activites')]
class Activite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int { return $this->id; }
    public function setId(int $id): self { $this->id = $id; return $this; }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le nom de l\'activité est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le nom doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nom = null;

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    #[ORM\Column(type: 'text', nullable: false)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 15,
        minMessage: 'La description doit comporter au moins {{ limit }} caractères.'
    )]
    private ?string $description = null;

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Assert\NotNull(message: 'Le budget est obligatoire.')]
    #[Assert\Positive(message: 'Le budget doit être un nombre positif.')]
    #[Assert\LessThanOrEqual(value: 100000, message: 'Le budget ne peut pas dépasser 100 000 DT.')]
    private ?int $budget = null;

    public function getBudget(): ?int { return $this->budget; }
    public function setBudget(int $budget): self { $this->budget = $budget; return $this; }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le niveau de difficulté est obligatoire.')]
    #[Assert\Choice(
        choices: ['facile', 'intermediaire', 'difficile', 'expert'],
        message: 'Veuillez choisir un niveau de difficulté valide.'
    )]
    private ?string $niveaudifficulte = null;

    public function getNiveaudifficulte(): ?string { return $this->niveaudifficulte; }
    public function setNiveaudifficulte(string $niveaudifficulte): self { $this->niveaudifficulte = $niveaudifficulte; return $this; }

    #[ORM\Column(type: 'string', nullable: true)]
    #[Assert\Length(
        max: 150,
        maxMessage: 'Le lieu ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $lieu = null;

    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(?string $lieu): self { $this->lieu = $lieu; return $this; }

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Assert\NotNull(message: 'L\'âge minimum est obligatoire.')]
    #[Assert\PositiveOrZero(message: 'L\'âge minimum ne peut pas être négatif.')]
    #[Assert\LessThanOrEqual(value: 120, message: 'L\'âge minimum ne peut pas dépasser 120 ans.')]
    private ?int $agemin = null;

    public function getAgemin(): ?int { return $this->agemin; }
    public function setAgemin(int $agemin): self { $this->agemin = $agemin; return $this; }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(
        choices: ['active', 'inactive', 'archivee'],
        message: 'Veuillez choisir un statut valide.'
    )]
    private ?string $statut = null;

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }

    #[ORM\Column(type: 'integer', nullable: false)]
    #[Assert\NotNull(message: 'La durée est obligatoire.')]
    #[Assert\Positive(message: 'La durée doit être supérieure à 0.')]
    #[Assert\LessThanOrEqual(value: 720, message: 'La durée ne peut pas dépasser 720 heures.')]
    private ?int $duree = null;

    public function getDuree(): ?int { return $this->duree; }
    public function setDuree(int $duree): self { $this->duree = $duree; return $this; }

    #[ORM\ManyToOne(targetEntity: Categorie::class, inversedBy: 'activites')]
    #[ORM\JoinColumn(name: 'categorie_id', referencedColumnName: 'id')]
    #[Assert\NotNull(message: 'Veuillez choisir une catégorie.')]
    private ?Categorie $categorie = null;

    public function getCategorie(): ?Categorie { return $this->categorie; }
    public function setCategorie(?Categorie $categorie): self { $this->categorie = $categorie; return $this; }

    #[ORM\Column(name: 'image_path', type: 'string', nullable: true)]
    private ?string $imagePath = null;

    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): self { $this->imagePath = $imagePath; return $this; }

    // ── Relations ────────────────────────────────────────────────────────────

    #[ORM\OneToMany(targetEntity: Etape::class, mappedBy: 'activite')]
    private Collection $etapes;

    #[ORM\OneToMany(targetEntity: Avis::class, mappedBy: 'activite', cascade: ['persist', 'remove'])]
    private Collection $avis;

    #[ORM\ManyToMany(targetEntity: Voyage::class, inversedBy: 'activites')]
    #[ORM\JoinTable(
        name: 'liste_activite',
        joinColumns: [new ORM\JoinColumn(name: 'id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'id_voyage', referencedColumnName: 'id_voyage')]
    )]
    private Collection $voyages;

    public function __construct()
    {
        $this->etapes  = new ArrayCollection();
        $this->avis    = new ArrayCollection();
        $this->voyages = new ArrayCollection();
    }

    // ── Etapes ───────────────────────────────────────────────────────────────

    /** @return Collection<int, Etape> */
    public function getEtapes(): Collection
    {
        if (!$this->etapes instanceof Collection) $this->etapes = new ArrayCollection();
        return $this->etapes;
    }

    public function addEtape(Etape $etape): self
    {
        if (!$this->getEtapes()->contains($etape)) $this->getEtapes()->add($etape);
        return $this;
    }

    public function removeEtape(Etape $etape): self
    {
        $this->getEtapes()->removeElement($etape);
        return $this;
    }

    // ── Avis ─────────────────────────────────────────────────────────────────

    /** @return Collection<int, Avis> */
    public function getAvis(): Collection
    {
        if (!$this->avis instanceof Collection) $this->avis = new ArrayCollection();
        return $this->avis;
    }

    public function addAvi(Avis $avi): self
    {
        if (!$this->getAvis()->contains($avi)) {
            $this->getAvis()->add($avi);
            $avi->setActivite($this);
        }
        return $this;
    }

    public function removeAvi(Avis $avi): self
    {
        $this->getAvis()->removeElement($avi);
        return $this;
    }

    public function getMoyenneNotes(): float
    {
        $avis = $this->getAvis();
        if ($avis->isEmpty()) return 0.0;
        $total = 0;
        foreach ($avis as $a) $total += $a->getNote();
        return round($total / $avis->count(), 1);
    }

    public function getMoyenneAvis(): float
    {
        return $this->getMoyenneNotes();
    }

    // ── Voyages ──────────────────────────────────────────────────────────────

    /** @return Collection<int, Voyage> */
    public function getVoyages(): Collection
    {
        if (!$this->voyages instanceof Collection) $this->voyages = new ArrayCollection();
        return $this->voyages;
    }

    public function addVoyage(Voyage $voyage): self
    {
        if (!$this->getVoyages()->contains($voyage)) $this->getVoyages()->add($voyage);
        return $this;
    }

    public function removeVoyage(Voyage $voyage): self
    {
        $this->getVoyages()->removeElement($voyage);
        return $this;
    }
}
