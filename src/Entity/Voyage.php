<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

use App\Repository\VoyageRepository;

#[ORM\Entity(repositoryClass: VoyageRepository::class)]
#[ORM\Table(name: 'voyage')]
class Voyage
{
    public const STATUTS = [
        'Planifie',
        'En cours',
        'Termine',
        'Annule',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_voyage = null;

    public function getId_voyage(): ?int
    {
        return $this->id_voyage;
    }

    public function setId_voyage(int $id_voyage): self
    {
        $this->id_voyage = $id_voyage;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le titre du voyage est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 120,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caracteres.',
        maxMessage: 'Le titre ne doit pas depasser {{ limit }} caracteres.'
    )]
    private ?string $titre_voyage = null;

    public function getTitre_voyage(): ?string
    {
        return $this->titre_voyage;
    }

    public function setTitre_voyage(string $titre_voyage): self
    {
        $this->titre_voyage = $titre_voyage;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Assert\NotNull(message: 'La date de debut est obligatoire.')]
    private ?\DateTimeInterface $date_debut = null;

    public function getDate_debut(): ?\DateTimeInterface
    {
        return $this->date_debut;
    }

    public function setDate_debut(\DateTimeInterface $date_debut): self
    {
        $this->date_debut = $date_debut;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    private ?\DateTimeInterface $date_fin = null;

    public function getDate_fin(): ?\DateTimeInterface
    {
        return $this->date_fin;
    }

    public function setDate_fin(\DateTimeInterface $date_fin): self
    {
        $this->date_fin = $date_fin;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    #[Assert\Choice(choices: self::STATUTS, message: 'Veuillez choisir un statut valide.')]
    private ?string $statut = null;

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Destination::class, inversedBy: 'voyages')]
    #[ORM\JoinColumn(name: 'id_destination', referencedColumnName: 'id_destination')]
    #[Assert\NotNull(message: 'La destination est obligatoire.')]
    private ?Destination $destination = null;

    public function getDestination(): ?Destination
    {
        return $this->destination;
    }

    public function setDestination(?Destination $destination): self
    {
        $this->destination = $destination;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Budget::class, mappedBy: 'voyage')]
    private Collection $budgets;

    /**
     * @return Collection<int, Budget>
     */
    public function getBudgets(): Collection
    {
        if (!$this->budgets instanceof Collection) {
            $this->budgets = new ArrayCollection();
        }
        return $this->budgets;
    }

    public function addBudget(Budget $budget): self
    {
        if (!$this->getBudgets()->contains($budget)) {
            $this->getBudgets()->add($budget);
        }
        return $this;
    }

    public function removeBudget(Budget $budget): self
    {
        $this->getBudgets()->removeElement($budget);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Itineraire::class, mappedBy: 'voyage')]
    private Collection $itineraires;

    /**
     * @return Collection<int, Itineraire>
     */
    public function getItineraires(): Collection
    {
        if (!$this->itineraires instanceof Collection) {
            $this->itineraires = new ArrayCollection();
        }
        return $this->itineraires;
    }

    public function addItineraire(Itineraire $itineraire): self
    {
        if (!$this->getItineraires()->contains($itineraire)) {
            $this->getItineraires()->add($itineraire);
        }
        return $this;
    }

    public function removeItineraire(Itineraire $itineraire): self
    {
        $this->getItineraires()->removeElement($itineraire);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'voyage')]
    private Collection $paiements;

    /**
     * @return Collection<int, Paiement>
     */
    public function getPaiements(): Collection
    {
        if (!$this->paiements instanceof Collection) {
            $this->paiements = new ArrayCollection();
        }
        return $this->paiements;
    }

    public function addPaiement(Paiement $paiement): self
    {
        if (!$this->getPaiements()->contains($paiement)) {
            $this->getPaiements()->add($paiement);
        }
        return $this;
    }

    public function removePaiement(Paiement $paiement): self
    {
        $this->getPaiements()->removeElement($paiement);
        return $this;
    }

    #[ORM\ManyToMany(targetEntity: Activite::class, mappedBy: 'voyages')]
    
    private Collection $activites;

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

    #[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'voyages')]
    
    private Collection $users;

    public function __construct()
    {
        $this->budgets = new ArrayCollection();
        $this->itineraires = new ArrayCollection();
        $this->paiements = new ArrayCollection();
        $this->activites = new ArrayCollection();
        $this->users = new ArrayCollection();
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        if (!$this->users instanceof Collection) {
            $this->users = new ArrayCollection();
        }
        return $this->users;
    }

    public function addUser(User $user): self
    {
        if (!$this->getUsers()->contains($user)) {
            $this->getUsers()->add($user);
        }
        return $this;
    }

    public function removeUser(User $user): self
    {
        $this->getUsers()->removeElement($user);
        return $this;
    }

    public function getIdVoyage(): ?int
    {
        return $this->id_voyage;
    }

    public static function getAvailableStatuts(): array
    {
        return self::STATUTS;
    }

    public function getTitreVoyage(): ?string
    {
        return $this->titre_voyage;
    }

    public function setTitreVoyage(string $titre_voyage): static
    {
        $this->titre_voyage = $titre_voyage;

        return $this;
    }

    public function getDateDebut(): ?\DateTime
    {
        return $this->date_debut;
    }

    public function setDateDebut(\DateTime $date_debut): static
    {
        $this->date_debut = $date_debut;

        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->date_fin;
    }

    public function setDateFin(\DateTime $date_fin): static
    {
        $this->date_fin = $date_fin;

        return $this;
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (!$this->date_debut instanceof \DateTimeInterface || !$this->date_fin instanceof \DateTimeInterface) {
            return;
        }

        if ($this->date_fin < $this->date_debut) {
            $context->buildViolation('La date de fin doit etre posterieure ou egale a la date de debut.')
                ->atPath('date_fin')
                ->addViolation();
        }
    }

}
