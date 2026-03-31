<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\CategorieRepository;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ORM\Table(name: 'categories')]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $nom = null;

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: false)]
    private ?string $description = null;

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $type = null;

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $saison = null;

    public function getSaison(): ?string
    {
        return $this->saison;
    }

    public function setSaison(string $saison): self
    {
        $this->saison = $saison;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $niveauintensite = null;

    public function getNiveauintensite(): ?string
    {
        return $this->niveauintensite;
    }

    public function setNiveauintensite(string $niveauintensite): self
    {
        $this->niveauintensite = $niveauintensite;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $publiccible = null;

    public function getPubliccible(): ?string
    {
        return $this->publiccible;
    }

    public function setPubliccible(string $publiccible): self
    {
        $this->publiccible = $publiccible;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Activite::class, mappedBy: 'categorie')]
    private Collection $activites;

    public function __construct()
    {
        $this->activites = new ArrayCollection();
    }

    /**
     * @return Collection<int, Activite>
     */
    public function getActivites(): Collection
    {
        if (!$this->activites instanceof Collection) {
            $this->activites = new ArrayCollection();
        }
        return $this->activites;
    }

    public function addActivite(Activite $activite): self
    {
        if (!$this->getActivites()->contains($activite)) {
            $this->getActivites()->add($activite);
        }
        return $this;
    }

    public function removeActivite(Activite $activite): self
    {
        $this->getActivites()->removeElement($activite);
        return $this;
    }

}
