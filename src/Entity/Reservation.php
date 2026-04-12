<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ReservationRepository;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservations')]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Activite::class, inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Activite $activite = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(type: 'string', length: 20)]
    private ?string $telephone = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?float $montantTotal = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?float $acompte = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $statutPaiement = 'en_attente';

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $methodeConfirmation = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $codeConfirmation = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $qrCodePath = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $dateReservation = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateConfirmation = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $transactionId = null;

    public function __construct()
    {
        $this->dateReservation = new \DateTime();
    }

    // Getters et Setters
    public function getId(): ?int { return $this->id; }

    public function getActivite(): ?Activite { return $this->activite; }
    public function setActivite(?Activite $activite): self { $this->activite = $activite; return $this; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): self { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): self { $this->prenom = $prenom; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(string $telephone): self { $this->telephone = $telephone; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): self { $this->commentaire = $commentaire; return $this; }

    public function getMontantTotal(): ?float { return $this->montantTotal; }
    public function setMontantTotal(float $montantTotal): self { $this->montantTotal = $montantTotal; return $this; }

    public function getAcompte(): ?float { return $this->acompte; }
    public function setAcompte(float $acompte): self { $this->acompte = $acompte; return $this; }

    public function getStatutPaiement(): ?string { return $this->statutPaiement; }
    public function setStatutPaiement(string $statutPaiement): self { $this->statutPaiement = $statutPaiement; return $this; }

    public function getMethodeConfirmation(): ?string { return $this->methodeConfirmation; }
    public function setMethodeConfirmation(?string $methodeConfirmation): self { $this->methodeConfirmation = $methodeConfirmation; return $this; }

    public function getCodeConfirmation(): ?string { return $this->codeConfirmation; }
    public function setCodeConfirmation(?string $codeConfirmation): self { $this->codeConfirmation = $codeConfirmation; return $this; }

    public function getQrCodePath(): ?string { return $this->qrCodePath; }
    public function setQrCodePath(?string $qrCodePath): self { $this->qrCodePath = $qrCodePath; return $this; }

    public function getDateReservation(): ?\DateTimeInterface { return $this->dateReservation; }
    public function setDateReservation(\DateTimeInterface $dateReservation): self { $this->dateReservation = $dateReservation; return $this; }

    public function getDateConfirmation(): ?\DateTimeInterface { return $this->dateConfirmation; }
    public function setDateConfirmation(?\DateTimeInterface $dateConfirmation): self { $this->dateConfirmation = $dateConfirmation; return $this; }

    public function getDatePaiement(): ?\DateTimeInterface { return $this->datePaiement; }
    public function setDatePaiement(?\DateTimeInterface $datePaiement): self { $this->datePaiement = $datePaiement; return $this; }

    public function getTransactionId(): ?string { return $this->transactionId; }
    public function setTransactionId(?string $transactionId): self { $this->transactionId = $transactionId; return $this; }

    public function getNomComplet(): string { return trim($this->prenom . ' ' . $this->nom); }

    public function isConfirmee(): bool { return $this->statutPaiement === 'confirme'; }

    public function generateCodeConfirmation(): string
    {
        $this->codeConfirmation = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        return $this->codeConfirmation;
    }
}
