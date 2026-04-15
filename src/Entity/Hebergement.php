<?php

namespace App\Entity;

use App\Repository\HebergementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[Vich\Uploadable]
#[ORM\Entity(repositoryClass: HebergementRepository::class)]
#[ORM\Table(name: 'hebergement')]
class Hebergement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_hebergement', type: Types::INTEGER)]
    private ?int $idHebergement = null;

    #[ORM\Column(name: 'nom_hebergement', type: Types::STRING, nullable: false)]
    private ?string $nomHebergement = null;

    #[ORM\Column(name: 'type_hebergement', type: Types::STRING, nullable: true)]
    private ?string $typeHebergement = null;

    #[ORM\Column(name: 'prix_nuit_hebergement', type: Types::FLOAT, nullable: true)]
    private ?float $prixNuitHebergement = null;

    #[ORM\Column(name: 'adresse_hebergement', type: Types::STRING, nullable: true)]
    private ?string $adresseHebergement = null;

    #[ORM\Column(name: 'note_hebergement', type: Types::FLOAT, nullable: true)]
    private ?float $noteHebergement = null;

    #[ORM\Column(name: 'latitude_hebergement', type: Types::FLOAT, nullable: true)]
    private ?float $latitudeHebergement = null;

    #[ORM\Column(name: 'longitude_hebergement', type: Types::FLOAT, nullable: true)]
    private ?float $longitudeHebergement = null;

    #[ORM\ManyToOne(targetEntity: Destination::class, inversedBy: 'hebergements')]
    #[ORM\JoinColumn(name: 'destination_hebergement', referencedColumnName: 'id_destination', onDelete: 'CASCADE')]
    private ?Destination $destination = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'hebergements')]
    #[ORM\JoinColumn(name: 'added_by', referencedColumnName: 'id')]
    private ?User $user = null;

    #[ORM\Column(name: 'image_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $imageName = null;

    #[Vich\UploadableField(mapping: 'hebergement_images', fileNameProperty: 'imageName')]
    private ?File $imageFile = null;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getIdHebergement(): ?int
    {
        return $this->idHebergement;
    }

    public function getNomHebergement(): ?string
    {
        return $this->nomHebergement;
    }

    public function setNomHebergement(string $nomHebergement): self
    {
        $this->nomHebergement = $nomHebergement;

        return $this;
    }

    public function getTypeHebergement(): ?string
    {
        return $this->typeHebergement;
    }

    public function setTypeHebergement(?string $typeHebergement): self
    {
        $this->typeHebergement = $typeHebergement;

        return $this;
    }

    public function getPrixNuitHebergement(): ?float
    {
        return $this->prixNuitHebergement;
    }

    public function setPrixNuitHebergement(?float $prixNuitHebergement): self
    {
        $this->prixNuitHebergement = $prixNuitHebergement;

        return $this;
    }

    public function getAdresseHebergement(): ?string
    {
        return $this->adresseHebergement;
    }

    public function setAdresseHebergement(?string $adresseHebergement): self
    {
        $this->adresseHebergement = $adresseHebergement;

        return $this;
    }

    public function getNoteHebergement(): ?float
    {
        return $this->noteHebergement;
    }

    public function setNoteHebergement(?float $noteHebergement): self
    {
        $this->noteHebergement = $noteHebergement;

        return $this;
    }

    public function getLatitudeHebergement(): ?float
    {
        return $this->latitudeHebergement;
    }

    public function setLatitudeHebergement(?float $latitudeHebergement): self
    {
        $this->latitudeHebergement = $latitudeHebergement;

        return $this;
    }

    public function getLongitudeHebergement(): ?float
    {
        return $this->longitudeHebergement;
    }

    public function setLongitudeHebergement(?float $longitudeHebergement): self
    {
        $this->longitudeHebergement = $longitudeHebergement;

        return $this;
    }

    public function getDestination(): ?Destination
    {
        return $this->destination;
    }

    public function setDestination(?Destination $destination): self
    {
        $this->destination = $destination;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

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
}
