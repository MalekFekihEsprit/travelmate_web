<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

use App\Repository\DestinationRepository;

#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: DestinationRepository::class)]
#[ORM\Table(name: 'destination')]
#[UniqueEntity(fields: ['nom_destination'], message: 'Une destination avec ce nom existe deja.', errorPath: 'nom_destination')]
class Destination
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_destination = null;

    public function getId_destination(): ?int
    {
        return $this->id_destination;
    }

    public function setId_destination(int $id_destination): self
    {
        $this->id_destination = $id_destination;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $nom_destination = null;

    public function getNom_destination(): ?string
    {
        return $this->nom_destination;
    }

    public function setNom_destination(string $nom_destination): self
    {
        $this->nom_destination = $nom_destination;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $pays_destination = null;

    public function getPays_destination(): ?string
    {
        return $this->pays_destination;
    }

    public function setPays_destination(string $pays_destination): self
    {
        $this->pays_destination = $pays_destination;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $region_destination = null;

    public function getRegion_destination(): ?string
    {
        return $this->region_destination;
    }

    public function setRegion_destination(?string $region_destination): self
    {
        $this->region_destination = $region_destination;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description_destination = null;

    public function getDescription_destination(): ?string
    {
        return $this->description_destination;
    }

    public function setDescription_destination(?string $description_destination): self
    {
        $this->description_destination = $description_destination;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $climat_destination = null;

    public function getClimat_destination(): ?string
    {
        return $this->climat_destination;
    }

    public function setClimat_destination(?string $climat_destination): self
    {
        $this->climat_destination = $climat_destination;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $saison_destination = null;

    public function getSaison_destination(): ?string
    {
        return $this->saison_destination;
    }

    public function setSaison_destination(?string $saison_destination): self
    {
        $this->saison_destination = $saison_destination;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude_destination = null;

    public function getLatitude_destination(): ?float
    {
        return $this->latitude_destination;
    }

    public function setLatitude_destination(?float $latitude_destination): self
    {
        $this->latitude_destination = $latitude_destination;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude_destination = null;

    public function getLongitude_destination(): ?float
    {
        return $this->longitude_destination;
    }

    public function setLongitude_destination(?float $longitude_destination): self
    {
        $this->longitude_destination = $longitude_destination;
        return $this;
    }

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private ?float $score_destination = 0.0;

    public function getScore_destination(): ?float
    {
        return $this->score_destination;
    }

    public function setScore_destination(?float $score_destination): self
    {
        $this->score_destination = $score_destination;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $currency_destination = null;

    public function getCurrency_destination(): ?string
    {
        return $this->currency_destination;
    }

    public function setCurrency_destination(?string $currency_destination): self
    {
        $this->currency_destination = $currency_destination;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $flag_destination = null;

    public function getFlag_destination(): ?string
    {
        return $this->flag_destination;
    }

    public function setFlag_destination(?string $flag_destination): self
    {
        $this->flag_destination = $flag_destination;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $languages_destination = null;

    public function getLanguages_destination(): ?string
    {
        return $this->languages_destination;
    }

    public function setLanguages_destination(?string $languages_destination): self
    {
        $this->languages_destination = $languages_destination;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $video_url = null;

    public function getVideo_url(): ?string
    {
        return $this->video_url;
    }

    public function setVideo_url(?string $video_url): self
    {
        $this->video_url = $video_url;
        return $this;
    }

    // ── VichUploader image fields ─────────────────────────────────────────────

    #[ORM\Column(name: 'image_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $imageName = null;

    #[Vich\UploadableField(mapping: 'destination_images', fileNameProperty: 'imageName')]
    private ?File $imageFile = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setImageName(?string $imageName): self
    {
        $this->imageName = $imageName;
        return $this;
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageFile(?File $imageFile): self
    {
        $this->imageFile = $imageFile;
        if ($imageFile !== null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'destinations')]
    #[ORM\JoinColumn(name: 'added_by', referencedColumnName: 'id')]
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

    #[ORM\OneToMany(targetEntity: Hebergement::class, mappedBy: 'destination')]
    private Collection $hebergements;

    #[ORM\OneToMany(targetEntity: Voyage::class, mappedBy: 'destination')]
    private Collection $voyages;

    #[ORM\OneToMany(targetEntity: NoteDestination::class, mappedBy: 'destination', orphanRemoval: true)]
    private Collection $notesDestination;

    #[ORM\OneToMany(targetEntity: FavoriteDestination::class, mappedBy: 'destination', orphanRemoval: true)]
    private Collection $favoriteDestinations;

    public function __construct()
    {
        $this->hebergements    = new ArrayCollection();
        $this->voyages         = new ArrayCollection();
        $this->notesDestination = new ArrayCollection();
        $this->favoriteDestinations = new ArrayCollection();
    }

    // ── Camel-case aliases (used by Twig & other controllers) ─────────────────

    public function getIdDestination(): ?int
    {
        return $this->id_destination;
    }

    public function getNomDestination(): ?string
    {
        return $this->nom_destination;
    }

    public function setNomDestination(string $nom_destination): static
    {
        $this->nom_destination = $nom_destination;
        return $this;
    }

    public function getPaysDestination(): ?string
    {
        return $this->pays_destination;
    }

    public function setPaysDestination(string $pays_destination): static
    {
        $this->pays_destination = $pays_destination;
        return $this;
    }

    public function getRegionDestination(): ?string
    {
        return $this->region_destination;
    }

    public function setRegionDestination(?string $region_destination): static
    {
        $this->region_destination = $region_destination;
        return $this;
    }

    public function getDescriptionDestination(): ?string
    {
        return $this->description_destination;
    }

    public function setDescriptionDestination(?string $description_destination): static
    {
        $this->description_destination = $description_destination;
        return $this;
    }

    public function getClimatDestination(): ?string
    {
        return $this->climat_destination;
    }

    public function setClimatDestination(?string $climat_destination): static
    {
        $this->climat_destination = $climat_destination;
        return $this;
    }

    public function getSaisonDestination(): ?string
    {
        return $this->saison_destination;
    }

    public function setSaisonDestination(?string $saison_destination): static
    {
        $this->saison_destination = $saison_destination;
        return $this;
    }

    public function getLatitudeDestination(): ?float
    {
        return $this->latitude_destination;
    }

    public function setLatitudeDestination(?float $latitude_destination): static
    {
        $this->latitude_destination = $latitude_destination;
        return $this;
    }

    public function getLongitudeDestination(): ?float
    {
        return $this->longitude_destination;
    }

    public function setLongitudeDestination(?float $longitude_destination): static
    {
        $this->longitude_destination = $longitude_destination;
        return $this;
    }

    public function getScoreDestination(): ?float
    {
        return $this->score_destination;
    }

    public function setScoreDestination(?float $score_destination): static
    {
        $this->score_destination = $score_destination;
        return $this;
    }

    public function getCurrencyDestination(): ?string
    {
        return $this->currency_destination;
    }

    public function setCurrencyDestination(?string $currency_destination): static
    {
        $this->currency_destination = $currency_destination;
        return $this;
    }

    public function getFlagDestination(): ?string
    {
        return $this->flag_destination;
    }

    public function setFlagDestination(?string $flag_destination): static
    {
        $this->flag_destination = $flag_destination;
        return $this;
    }

    public function getLanguagesDestination(): ?string
    {
        return $this->languages_destination;
    }

    public function setLanguagesDestination(?string $languages_destination): static
    {
        $this->languages_destination = $languages_destination;
        return $this;
    }

    public function getVideoUrl(): ?string
    {
        return $this->video_url;
    }

    public function setVideoUrl(?string $video_url): static
    {
        $this->video_url = $video_url;
        return $this;
    }

    // ── Collection helpers ────────────────────────────────────────────────────

    public function getHebergements(): Collection
    {
        if (!$this->hebergements instanceof Collection) {
            $this->hebergements = new ArrayCollection();
        }
        return $this->hebergements;
    }

    public function addHebergement(Hebergement $hebergement): self
    {
        if (!$this->getHebergements()->contains($hebergement)) {
            $this->getHebergements()->add($hebergement);
        }
        return $this;
    }

    public function removeHebergement(Hebergement $hebergement): self
    {
        $this->getHebergements()->removeElement($hebergement);
        return $this;
    }

    public function getVoyages(): Collection
    {
        if (!$this->voyages instanceof Collection) {
            $this->voyages = new ArrayCollection();
        }
        return $this->voyages;
    }

    public function addVoyage(Voyage $voyage): self
    {
        if (!$this->getVoyages()->contains($voyage)) {
            $this->getVoyages()->add($voyage);
        }
        return $this;
    }

    public function removeVoyage(Voyage $voyage): self
    {
        $this->getVoyages()->removeElement($voyage);
        return $this;
    }

    public function getNotesDestination(): Collection
    {
        if (!$this->notesDestination instanceof Collection) {
            $this->notesDestination = new ArrayCollection();
        }
        return $this->notesDestination;
    }

    public function addNoteDestination(NoteDestination $noteDestination): self
    {
        if (!$this->getNotesDestination()->contains($noteDestination)) {
            $this->getNotesDestination()->add($noteDestination);
            $noteDestination->setDestination($this);
        }
        return $this;
    }

    public function removeNoteDestination(NoteDestination $noteDestination): self
    {
        if ($this->getNotesDestination()->removeElement($noteDestination)) {
            if ($noteDestination->getDestination() === $this) {
                $noteDestination->setDestination(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, FavoriteDestination>
     */
    public function getFavoriteDestinations(): Collection
    {
        if (!$this->favoriteDestinations instanceof Collection) {
            $this->favoriteDestinations = new ArrayCollection();
        }

        return $this->favoriteDestinations;
    }

    public function addFavoriteDestination(FavoriteDestination $favoriteDestination): self
    {
        if (!$this->getFavoriteDestinations()->contains($favoriteDestination)) {
            $this->getFavoriteDestinations()->add($favoriteDestination);
            $favoriteDestination->setDestination($this);
        }

        return $this;
    }

    public function removeFavoriteDestination(FavoriteDestination $favoriteDestination): self
    {
        if ($this->getFavoriteDestinations()->removeElement($favoriteDestination)) {
            if ($favoriteDestination->getDestination() === $this) {
                $favoriteDestination->setDestination(null);
            }
        }

        return $this;
    }
}