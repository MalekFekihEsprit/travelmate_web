<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\ItineraireRepository;

#[ORM\Entity(repositoryClass: ItineraireRepository::class)]
#[ORM\Table(name: 'itineraire')]
class Itineraire
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_itineraire = null;

    public function getId_itineraire(): ?int
    {
        return $this->id_itineraire;
    }

    public function setId_itineraire(int $id_itineraire): self
    {
        $this->id_itineraire = $id_itineraire;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $nom_itineraire = null;

    public function getNom_itineraire(): ?string
    {
        return $this->nom_itineraire;
    }

    public function setNom_itineraire(string $nom_itineraire): self
    {
        $this->nom_itineraire = $nom_itineraire;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $description_itineraire = null;

    public function getDescription_itineraire(): ?string
    {
        return $this->description_itineraire;
    }

    public function setDescription_itineraire(string $description_itineraire): self
    {
        $this->description_itineraire = $description_itineraire;
        return $this;
    }

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $jaime = 0;

    public function getJaime(): int
    {
        return $this->jaime;
    }

    public function setJaime(int $jaime): self
    {
        $this->jaime = $jaime;
        return $this;
    }

    public function incrementJaime(): self
    {
        $this->jaime++;
        return $this;
    }

    public function decrementJaime(): self
    {
        if ($this->jaime > 0) {
            $this->jaime--;
        }
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: Voyage::class, inversedBy: 'itineraires')]
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

    #[ORM\OneToMany(targetEntity: Etape::class, mappedBy: 'itineraire', cascade: ['remove'], orphanRemoval: true)]
    private Collection $etapes;

    public function __construct()
    {
        $this->etapes = new ArrayCollection();
    }

    /**
     * @return Collection<int, Etape>
     */
    public function getEtapes(): Collection
    {
        if (!$this->etapes instanceof Collection) {
            $this->etapes = new ArrayCollection();
        }
        return $this->etapes;
    }

    public function addEtape(Etape $etape): self
    {
        if (!$this->getEtapes()->contains($etape)) {
            $this->getEtapes()->add($etape);
        }
        return $this;
    }

    public function removeEtape(Etape $etape): self
    {
        $this->getEtapes()->removeElement($etape);
        return $this;
    }

    public function getIdItineraire(): ?int
    {
        return $this->id_itineraire;
    }

    public function getNomItineraire(): ?string
    {
        return $this->nom_itineraire;
    }

    public function setNomItineraire(string $nom_itineraire): static
    {
        $this->nom_itineraire = $nom_itineraire;

        return $this;
    }

    public function getDescriptionItineraire(): ?string
    {
        return $this->description_itineraire;
    }

    public function setDescriptionItineraire(string $description_itineraire): static
    {
        $this->description_itineraire = $description_itineraire;

        return $this;
    }

}
