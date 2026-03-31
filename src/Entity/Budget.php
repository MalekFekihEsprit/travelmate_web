<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\BudgetRepository;

#[ORM\Entity(repositoryClass: BudgetRepository::class)]
#[ORM\Table(name: 'budget')]
class Budget
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_budget = null;

    public function getId_budget(): ?int
    {
        return $this->id_budget;
    }

    public function setId_budget(int $id_budget): self
    {
        $this->id_budget = $id_budget;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $libelle_budget = null;

    public function getLibelle_budget(): ?string
    {
        return $this->libelle_budget;
    }

    public function setLibelle_budget(string $libelle_budget): self
    {
        $this->libelle_budget = $libelle_budget;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $montant_total = null;

    public function getMontant_total(): ?float
    {
        return $this->montant_total;
    }

    public function setMontant_total(float $montant_total): self
    {
        $this->montant_total = $montant_total;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $devise_budget = null;

    public function getDevise_budget(): ?string
    {
        return $this->devise_budget;
    }

    public function setDevise_budget(?string $devise_budget): self
    {
        $this->devise_budget = $devise_budget;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $statut_budget = null;

    public function getStatut_budget(): ?string
    {
        return $this->statut_budget;
    }

    public function setStatut_budget(?string $statut_budget): self
    {
        $this->statut_budget = $statut_budget;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description_budget = null;

    public function getDescription_budget(): ?string
    {
        return $this->description_budget;
    }

    public function setDescription_budget(?string $description_budget): self
    {
        $this->description_budget = $description_budget;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'budgets')]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'id')]
    private ?User $user = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Voyage::class, inversedBy: 'budgets')]
    #[ORM\JoinColumn(name: 'id_voyage', referencedColumnName: 'id_voyage')]
    private ?Voyage $voyage = null;

    public function getVoyage(): ?Voyage
    {
        return $this->voyage;
    }

    public function setVoyage(?Voyage $voyage): self
    {
        $this->voyage = $voyage;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Depense::class, mappedBy: 'budget')]
    private Collection $depenses;

    public function __construct()
    {
        $this->depenses = new ArrayCollection();
    }

    /**
     * @return Collection<int, Depense>
     */
    public function getDepenses(): Collection
    {
        if (!$this->depenses instanceof Collection) {
            $this->depenses = new ArrayCollection();
        }
        return $this->depenses;
    }

    public function addDepense(Depense $depense): self
    {
        if (!$this->getDepenses()->contains($depense)) {
            $this->getDepenses()->add($depense);
        }
        return $this;
    }

    public function removeDepense(Depense $depense): self
    {
        $this->getDepenses()->removeElement($depense);
        return $this;
    }

    public function getIdBudget(): ?int
    {
        return $this->id_budget;
    }

    public function getLibelleBudget(): ?string
    {
        return $this->libelle_budget;
    }

    public function setLibelleBudget(string $libelle_budget): static
    {
        $this->libelle_budget = $libelle_budget;

        return $this;
    }

    public function getMontantTotal(): ?string
    {
        return $this->montant_total;
    }

    public function setMontantTotal(string $montant_total): static
    {
        $this->montant_total = $montant_total;

        return $this;
    }

    public function getDeviseBudget(): ?string
    {
        return $this->devise_budget;
    }

    public function setDeviseBudget(?string $devise_budget): static
    {
        $this->devise_budget = $devise_budget;

        return $this;
    }

    public function getStatutBudget(): ?string
    {
        return $this->statut_budget;
    }

    public function setStatutBudget(?string $statut_budget): static
    {
        $this->statut_budget = $statut_budget;

        return $this;
    }

    public function getDescriptionBudget(): ?string
    {
        return $this->description_budget;
    }

    public function setDescriptionBudget(?string $description_budget): static
    {
        $this->description_budget = $description_budget;

        return $this;
    }

}
