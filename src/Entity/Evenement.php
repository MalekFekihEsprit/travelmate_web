<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\EvenementRepository;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\Table(name: 'evenement')]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int { return $this->id; }

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Le titre de l\'événement est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: 'Le titre doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $titre = null;

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        min: 15,
        minMessage: 'La description doit comporter au moins {{ limit }} caractères.'
    )]
    private ?string $description = null;

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    #[ORM\Column(type: 'date', nullable: false)]
    #[Assert\NotNull(message: 'La date est obligatoire.')]
    #[Assert\GreaterThanOrEqual(
        value: 'today',
        message: 'La date de l\'événement ne peut pas être dans le passé.'
    )]
    private ?\DateTimeInterface $date = null;

    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }

    #[ORM\Column(type: 'time', nullable: false)]
    #[Assert\NotNull(message: 'L\'heure est obligatoire.')]
    private ?\DateTimeInterface $heure = null;

    public function getHeure(): ?\DateTimeInterface { return $this->heure; }
    public function setHeure(\DateTimeInterface $heure): self { $this->heure = $heure; return $this; }

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le lieu doit comporter au moins {{ limit }} caractères.',
        maxMessage: 'Le lieu ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $lieu = null;

    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(string $lieu): self { $this->lieu = $lieu; return $this; }

    #[ORM\Column(name: 'latitude', type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(name: 'longitude', type: 'float', nullable: true)]
    private ?float $longitude = null;

    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): self { $this->latitude = $latitude; return $this; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): self { $this->longitude = $longitude; return $this; }

    #[ORM\Column(name: 'nb_places', type: 'integer', nullable: false)]
    #[Assert\NotNull(message: 'Le nombre de places est obligatoire.')]
    #[Assert\Positive(message: 'Le nombre de places doit être supérieur à 0.')]
    #[Assert\LessThanOrEqual(value: 10000, message: 'Le nombre de places ne peut pas dépasser 10 000.')]
    private ?int $nbPlaces = null;

    public function getNbPlaces(): ?int { return $this->nbPlaces; }
    public function setNbPlaces(int $nbPlaces): self { $this->nbPlaces = $nbPlaces; return $this; }

    // ──────────────────────────────────────────────────────────────
    // lienGroupe : stocke le lien d'invitation Telegram de l'événement
    // Ce champ existait déjà — on le réutilise pour Telegram.
    // Pas de migration nécessaire si la colonne existe déjà en base.
    // ──────────────────────────────────────────────────────────────
    #[ORM\Column(name: 'lien_groupe', type: 'string', length: 500, nullable: true)]
    #[Assert\Url(message: 'Veuillez saisir une URL valide (ex: https://...)')]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Le lien ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $lienGroupe = null;

    public function getLienGroupe(): ?string { return $this->lienGroupe; }
    public function setLienGroupe(?string $lienGroupe): self { $this->lienGroupe = $lienGroupe; return $this; }

    #[ORM\Column(name: 'image_path', type: 'string', length: 255, nullable: true)]
    private ?string $imagePath = null;

    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): self { $this->imagePath = $imagePath; return $this; }

    // ──────────────────────────────────────────────────────────────
    // telegramGroupId : ID interne du groupe Telegram (ex: -100123456789)
    // Nouveau champ — nécessite une migration Doctrine (voir ci-dessous)
    // ──────────────────────────────────────────────────────────────
    #[ORM\Column(name: 'telegram_group_id', type: 'string', length: 100, nullable: true)]
    private ?string $telegramGroupId = null;

    public function getTelegramGroupId(): ?string { return $this->telegramGroupId; }
    public function setTelegramGroupId(?string $telegramGroupId): self { $this->telegramGroupId = $telegramGroupId; return $this; }

    #[ORM\OneToMany(targetEntity: Participationevenement::class, mappedBy: 'evenement', cascade: ['persist', 'remove'])]
    private Collection $participations;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
    }

    /** @return Collection<int, Participationevenement> */
    public function getParticipations(): Collection
    {
        if (!$this->participations instanceof Collection) $this->participations = new ArrayCollection();
        return $this->participations;
    }

    public function addParticipation(Participationevenement $p): self
    {
        if (!$this->getParticipations()->contains($p)) {
            $this->getParticipations()->add($p);
            $p->setEvenement($this);
        }
        return $this;
    }

    public function removeParticipation(Participationevenement $p): self
    {
        $this->getParticipations()->removeElement($p);
        return $this;
    }

    public function getPlacesRestantes(): int
    {
        return max(0, $this->nbPlaces - $this->getParticipations()->count());
    }

    public function isComplet(): bool
    {
        return $this->getPlacesRestantes() === 0;
    }
}