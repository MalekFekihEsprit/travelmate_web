<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\PaiementRepository;

#[ORM\Entity(repositoryClass: PaiementRepository::class)]
#[ORM\Table(name: 'paiement')]
class Paiement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_paiement = null;

    public function getId_paiement(): ?int
    {
        return $this->id_paiement;
    }

    public function setId_paiement(int $id_paiement): self
    {
        $this->id_paiement = $id_paiement;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Voyage::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(name: 'id_voyage', referencedColumnName: 'id_voyage', onDelete: 'CASCADE')]
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'paiements')]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id')]
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

    #[ORM\Column(type: 'float', nullable: false)]
    private ?float $montant = null;

    public function getMontant(): ?float
    {
        return $this->montant;
    }

    public function setMontant(float $montant): self
    {
        $this->montant = $montant;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $devise = null;

    public function getDevise(): ?string
    {
        return $this->devise;
    }

    public function setDevise(string $devise): self
    {
        $this->devise = $devise;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $methode = null;

    public function getMethode(): ?string
    {
        return $this->methode;
    }

    public function setMethode(string $methode): self
    {
        $this->methode = $methode;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
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

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $transaction_id = null;

    public function getTransaction_id(): ?string
    {
        return $this->transaction_id;
    }

    public function setTransaction_id(?string $transaction_id): self
    {
        $this->transaction_id = $transaction_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $sale_id = null;

    public function getSale_id(): ?string
    {
        return $this->sale_id;
    }

    public function setSale_id(?string $sale_id): self
    {
        $this->sale_id = $sale_id;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $date_paiement = null;

    public function getDate_paiement(): ?\DateTimeInterface
    {
        return $this->date_paiement;
    }

    public function setDate_paiement(\DateTimeInterface $date_paiement): self
    {
        $this->date_paiement = $date_paiement;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $email_payeur = null;

    public function getEmail_payeur(): ?string
    {
        return $this->email_payeur;
    }

    public function setEmail_payeur(?string $email_payeur): self
    {
        $this->email_payeur = $email_payeur;
        return $this;
    }

    public function getIdPaiement(): ?int
    {
        return $this->id_paiement;
    }

    public function getTransactionId(): ?string
    {
        return $this->transaction_id;
    }

    public function setTransactionId(?string $transaction_id): static
    {
        $this->transaction_id = $transaction_id;

        return $this;
    }

    public function getSaleId(): ?string
    {
        return $this->sale_id;
    }

    public function setSaleId(?string $sale_id): static
    {
        $this->sale_id = $sale_id;

        return $this;
    }

    public function getDatePaiement(): ?\DateTime
    {
        return $this->date_paiement;
    }

    public function setDatePaiement(\DateTime $date_paiement): static
    {
        $this->date_paiement = $date_paiement;

        return $this;
    }

    public function getEmailPayeur(): ?string
    {
        return $this->email_payeur;
    }

    public function setEmailPayeur(?string $email_payeur): static
    {
        $this->email_payeur = $email_payeur;

        return $this;
    }

}
