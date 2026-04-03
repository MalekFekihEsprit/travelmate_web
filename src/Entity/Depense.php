<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\DepenseRepository;

#[ORM\Entity(repositoryClass: DepenseRepository::class)]
#[ORM\Table(name: 'depense')]
class Depense
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_depense = null;

    public function getId_depense(): ?int
    {
        return $this->id_depense;
    }

    public function setId_depense(int $id_depense): self
    {
        $this->id_depense = $id_depense;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $montant_depense = null;

    public function getMontant_depense(): ?float
    {
        return $this->montant_depense;
    }

    public function setMontant_depense(float $montant_depense): self
    {
        $this->montant_depense = $montant_depense;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $libelle_depense = null;

    public function getLibelle_depense(): ?string
    {
        return $this->libelle_depense;
    }

    public function setLibelle_depense(string $libelle_depense): self
    {
        $this->libelle_depense = $libelle_depense;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $categorie_depense = null;

    public function getCategorie_depense(): ?string
    {
        return $this->categorie_depense;
    }

    public function setCategorie_depense(string $categorie_depense): self
    {
        $this->categorie_depense = $categorie_depense;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $description_depense = null;

    public function getDescription_depense(): ?string
    {
        return $this->description_depense;
    }

    public function setDescription_depense(string $description_depense): self
    {
        $this->description_depense = $description_depense;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $devise_depense = null;

    public function getDevise_depense(): ?string
    {
        return $this->devise_depense;
    }

    public function setDevise_depense(?string $devise_depense): self
    {
        $this->devise_depense = $devise_depense;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $type_paiement = null;

    public function getType_paiement(): ?string
    {
        return $this->type_paiement;
    }

    public function setType_paiement(string $type_paiement): self
    {
        $this->type_paiement = $type_paiement;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $date_creation = null;

    public function getDate_creation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDate_creation(\DateTimeInterface $date_creation): self
    {
        $this->date_creation = $date_creation;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Budget::class, inversedBy: 'depenses')]
    #[ORM\JoinColumn(name: 'id_budget', referencedColumnName: 'id_budget', onDelete: 'CASCADE')]
    private ?Budget $budget = null;

    public function getBudget(): ?Budget
    {
        return $this->budget;
    }

    public function setBudget(?Budget $budget): self
    {
        $this->budget = $budget;
        return $this;
    }

    public function getIdDepense(): ?int
    {
        return $this->id_depense;
    }

    public function getMontantDepense(): ?string
    {
        return $this->montant_depense;
    }

    public function setMontantDepense(string $montant_depense): static
    {
        $this->montant_depense = $montant_depense;

        return $this;
    }

    public function getLibelleDepense(): ?string
    {
        return $this->libelle_depense;
    }

    public function setLibelleDepense(string $libelle_depense): static
    {
        $this->libelle_depense = $libelle_depense;

        return $this;
    }

    public function getCategorieDepense(): ?string
    {
        return $this->categorie_depense;
    }

    public function setCategorieDepense(string $categorie_depense): static
    {
        $this->categorie_depense = $categorie_depense;

        return $this;
    }

    public function getDescriptionDepense(): ?string
    {
        return $this->description_depense;
    }

    public function setDescriptionDepense(string $description_depense): static
    {
        $this->description_depense = $description_depense;

        return $this;
    }

    public function getDeviseDepense(): ?string
    {
        return $this->devise_depense;
    }

    public function setDeviseDepense(?string $devise_depense): static
    {
        $this->devise_depense = $devise_depense;

        return $this;
    }

    public function getTypePaiement(): ?string
    {
        return $this->type_paiement;
    }

    public function setTypePaiement(string $type_paiement): static
    {
        $this->type_paiement = $type_paiement;

        return $this;
    }

    public function getDateCreation(): ?\DateTime
    {
        return $this->date_creation;
    }

    public function setDateCreation(\DateTime $date_creation): static
    {
        $this->date_creation = $date_creation;

        return $this;
    }

}
