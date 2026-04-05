<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\EvenementRepository;

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
    private ?string $titre = null;

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $date = null;

    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): self { $this->date = $date; return $this; }

    #[ORM\Column(type: 'time', nullable: false)]
    private ?\DateTimeInterface $heure = null;

    public function getHeure(): ?\DateTimeInterface { return $this->heure; }
    public function setHeure(\DateTimeInterface $heure): self { $this->heure = $heure; return $this; }

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private ?string $lieu = null;

    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(string $lieu): self { $this->lieu = $lieu; return $this; }

    #[ORM\Column(name: 'nb_places', type: 'integer', nullable: false)]
    private ?int $nbPlaces = null;

    public function getNbPlaces(): ?int { return $this->nbPlaces; }
    public function setNbPlaces(int $nbPlaces): self { $this->nbPlaces = $nbPlaces; return $this; }

    #[ORM\Column(name: 'lien_groupe', type: 'string', length: 500, nullable: true)]
    private ?string $lienGroupe = null;

    public function getLienGroupe(): ?string { return $this->lienGroupe; }
    public function setLienGroupe(?string $lienGroupe): self { $this->lienGroupe = $lienGroupe; return $this; }

    #[ORM\Column(name: 'image_path', type: 'string', length: 255, nullable: true)]
    private ?string $imagePath = null;

    public function getImagePath(): ?string { return $this->imagePath; }
    public function setImagePath(?string $imagePath): self { $this->imagePath = $imagePath; return $this; }

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
