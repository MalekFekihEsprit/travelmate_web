<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\HebergementRepository;

#[ORM\Entity(repositoryClass: HebergementRepository::class)]
#[ORM\Table(name: 'hebergement')]
class Hebergement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_hebergement = null;

    public function getId_hebergement(): ?int
    {
        return $this->id_hebergement;
    }

    public function setId_hebergement(int $id_hebergement): self
    {
        $this->id_hebergement = $id_hebergement;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $nom_hebergement = null;

    public function getNom_hebergement(): ?string
    {
        return $this->nom_hebergement;
    }

    public function setNom_hebergement(string $nom_hebergement): self
    {
        $this->nom_hebergement = $nom_hebergement;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $type_hebergement = null;

    public function getType_hebergement(): ?string
    {
        return $this->type_hebergement;
    }

    public function setType_hebergement(?string $type_hebergement): self
    {
        $this->type_hebergement = $type_hebergement;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $prixNuit_hebergement = null;

    public function getPrixNuit_hebergement(): ?float
    {
        return $this->prixNuit_hebergement;
    }

    public function setPrixNuit_hebergement(?float $prixNuit_hebergement): self
    {
        $this->prixNuit_hebergement = $prixNuit_hebergement;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $adresse_hebergement = null;

    public function getAdresse_hebergement(): ?string
    {
        return $this->adresse_hebergement;
    }

    public function setAdresse_hebergement(?string $adresse_hebergement): self
    {
        $this->adresse_hebergement = $adresse_hebergement;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $note_hebergement = null;

    public function getNote_hebergement(): ?float
    {
        return $this->note_hebergement;
    }

    public function setNote_hebergement(?float $note_hebergement): self
    {
        $this->note_hebergement = $note_hebergement;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude_hebergement = null;

    public function getLatitude_hebergement(): ?float
    {
        return $this->latitude_hebergement;
    }

    public function setLatitude_hebergement(?float $latitude_hebergement): self
    {
        $this->latitude_hebergement = $latitude_hebergement;
        return $this;
    }

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude_hebergement = null;

    public function getLongitude_hebergement(): ?float
    {
        return $this->longitude_hebergement;
    }

    public function setLongitude_hebergement(?float $longitude_hebergement): self
    {
        $this->longitude_hebergement = $longitude_hebergement;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Destination::class, inversedBy: 'hebergements')]
    #[ORM\JoinColumn(name: 'destination_hebergement', referencedColumnName: 'id_destination')]
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

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'hebergements')]
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

    public function getIdHebergement(): ?int
    {
        return $this->id_hebergement;
    }

    public function getNomHebergement(): ?string
    {
        return $this->nom_hebergement;
    }

    public function setNomHebergement(string $nom_hebergement): static
    {
        $this->nom_hebergement = $nom_hebergement;

        return $this;
    }

    public function getTypeHebergement(): ?string
    {
        return $this->type_hebergement;
    }

    public function setTypeHebergement(?string $type_hebergement): static
    {
        $this->type_hebergement = $type_hebergement;

        return $this;
    }

    public function getPrixNuitHebergement(): ?string
    {
        return $this->prixNuit_hebergement;
    }

    public function setPrixNuitHebergement(?string $prixNuit_hebergement): static
    {
        $this->prixNuit_hebergement = $prixNuit_hebergement;

        return $this;
    }

    public function getAdresseHebergement(): ?string
    {
        return $this->adresse_hebergement;
    }

    public function setAdresseHebergement(?string $adresse_hebergement): static
    {
        $this->adresse_hebergement = $adresse_hebergement;

        return $this;
    }

    public function getNoteHebergement(): ?string
    {
        return $this->note_hebergement;
    }

    public function setNoteHebergement(?string $note_hebergement): static
    {
        $this->note_hebergement = $note_hebergement;

        return $this;
    }

    public function getLatitudeHebergement(): ?string
    {
        return $this->latitude_hebergement;
    }

    public function setLatitudeHebergement(?string $latitude_hebergement): static
    {
        $this->latitude_hebergement = $latitude_hebergement;

        return $this;
    }

    public function getLongitudeHebergement(): ?string
    {
        return $this->longitude_hebergement;
    }

    public function setLongitudeHebergement(?string $longitude_hebergement): static
    {
        $this->longitude_hebergement = $longitude_hebergement;

        return $this;
    }

}
