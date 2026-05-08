<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\VoyageRepository;

#[ORM\Entity(repositoryClass: VoyageRepository::class)]
#[ORM\Table(name: 'voyage')]
class Voyage
{
    public const STATUTS = ['Planifie', 'En cours', 'Termine', 'Annule'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_voyage = null;

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 120)]
    private ?string $titre_voyage = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $date_debut = null;

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Assert\NotNull]
    private ?\DateTimeInterface $date_fin = null;

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::STATUTS)]
    private ?string $statut = null;

    #[ORM\ManyToOne(targetEntity: Destination::class, inversedBy: 'voyages')]
    #[ORM\JoinColumn(name: 'id_destination', referencedColumnName: 'id_destination', onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Destination $destination = null;

    #[ORM\OneToMany(targetEntity: Budget::class, mappedBy: 'voyage', cascade: ['remove'], orphanRemoval: true)]
    private Collection $budgets;

    #[ORM\OneToMany(targetEntity: Itineraire::class, mappedBy: 'voyage', cascade: ['remove'], orphanRemoval: true)]
    private Collection $itineraires;

    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'voyage', cascade: ['remove'], orphanRemoval: true)]
    private Collection $paiements;

    #[ORM\ManyToMany(targetEntity: Activite::class, mappedBy: 'voyages')]
    private Collection $activites;

    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'voyage', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participations;

    public function __construct()
    {
        $this->budgets = new ArrayCollection();
        $this->itineraires = new ArrayCollection();
        $this->paiements = new ArrayCollection();
        $this->activites = new ArrayCollection();
        $this->participations = new ArrayCollection();
    }

    // ---------- ID ----------
    public function getIdVoyage(): ?int
    {
        return $this->id_voyage;
    }

    // ---------- Titre ----------
    public function getTitreVoyage(): ?string
    {
        return $this->titre_voyage;
    }

    public function setTitreVoyage(string $titre_voyage): static
    {
        $this->titre_voyage = $titre_voyage;
        return $this;
    }

    // ---------- Dates ----------
    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->date_debut;
    }

    public function setDateDebut(\DateTimeInterface $date_debut): static
    {
        $this->date_debut = $date_debut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->date_fin;
    }

    public function setDateFin(\DateTimeInterface $date_fin): static
    {
        $this->date_fin = $date_fin;
        return $this;
    }

    // ---------- Statut ----------
    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    // ---------- Destination ----------
    public function getDestination(): ?Destination
    {
        return $this->destination;
    }

    public function setDestination(?Destination $destination): static
    {
        $this->destination = $destination;
        return $this;
    }

    // ---------- Collections ----------
    public function getBudgets(): Collection { return $this->budgets; }
    public function getItineraires(): Collection { return $this->itineraires; }
    public function getPaiements(): Collection { return $this->paiements; }
    public function getActivites(): Collection { return $this->activites; }
    public function getParticipations(): Collection { return $this->participations; }

    public function addActivite(Activite $activite): static
    {
        if (!$this->activites->contains($activite)) {
            $this->activites->add($activite);
            $activite->addVoyage($this);
        }
        return $this;
    }

    public function removeActivite(Activite $activite): static
    {
        if ($this->activites->removeElement($activite)) {
            $activite->removeVoyage($this);
        }
        return $this;
    }

    // Helper for static status list
    public static function getAvailableStatuts(): array
    {
        return self::STATUTS;
    }

    // Custom validation callback
    #[Assert\Callback]
    public function validateDates(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if (!$this->date_debut || !$this->date_fin) {
            return;
        }
        if ($this->date_fin < $this->date_debut) {
            $context->buildViolation('La date de fin doit être postérieure ou égale à la date de début.')
                ->atPath('date_fin')
                ->addViolation();
        }
    }
}