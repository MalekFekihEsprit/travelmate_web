<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\EtapeRepository;

#[ORM\Entity(repositoryClass: EtapeRepository::class)]
#[ORM\Table(name: 'etape')]
class Etape
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_etape = null;

    public function getId_etape(): ?int
    {
        return $this->id_etape;
    }

    public function setId_etape(int $id_etape): self
    {
        $this->id_etape = $id_etape;
        return $this;
    }

    #[ORM\Column(type: 'time', nullable: false)]
    private ?\DateTimeInterface $heure = null;

    public function getHeure(): ?\DateTimeInterface
    {
        return $this->heure;
    }

    public function setHeure(\DateTimeInterface $heure): self
    {
        $this->heure = $heure;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $description_etape = null;

    public function getDescription_etape(): ?string
    {
        return $this->description_etape;
    }

    public function setDescription_etape(string $description_etape): self
    {
        $this->description_etape = $description_etape;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Activite::class, inversedBy: 'etapes')]
    #[ORM\JoinColumn(name: 'id_activite', referencedColumnName: 'id')]
    private ?Activite $activite = null;

    public function getActivite(): ?Activite
    {
        return $this->activite;
    }

    public function setActivite(?Activite $activite): self
    {
        $this->activite = $activite;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Itineraire::class, inversedBy: 'etapes')]
    #[ORM\JoinColumn(name: 'id_itineraire', referencedColumnName: 'id_itineraire', onDelete: 'CASCADE')]
    private ?Itineraire $itineraire = null;

    public function getItineraire(): ?Itineraire
    {
        return $this->itineraire;
    }

    public function setItineraire(?Itineraire $itineraire): self
    {
        $this->itineraire = $itineraire;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $numero_jour = null;

    public function getNumero_jour(): ?int
    {
        return $this->numero_jour;
    }

    public function setNumero_jour(int $numero_jour): self
    {
        $this->numero_jour = $numero_jour;
        return $this;
    }

    public function getIdEtape(): ?int
    {
        return $this->id_etape;
    }

    public function getDescriptionEtape(): ?string
    {
        return $this->description_etape;
    }

    public function setDescriptionEtape(string $description_etape): static
    {
        $this->description_etape = $description_etape;

        return $this;
    }

    public function getNumeroJour(): ?int
    {
        return $this->numero_jour;
    }

    public function setNumeroJour(int $numero_jour): static
    {
        $this->numero_jour = $numero_jour;

        return $this;
    }

}
