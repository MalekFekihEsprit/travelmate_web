<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\UserRepository;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
class User
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $prenom = null;

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;
        return $this;
    }

    #[ORM\Column(type: 'date', nullable: false)]
    private ?\DateTimeInterface $date_naissance = null;

    public function getDate_naissance(): ?\DateTimeInterface
    {
        return $this->date_naissance;
    }

    public function setDate_naissance(\DateTimeInterface $date_naissance): self
    {
        $this->date_naissance = $date_naissance;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $email = null;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $telephone = null;

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): self
    {
        $this->telephone = $telephone;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $mot_de_passe = null;

    public function getMot_de_passe(): ?string
    {
        return $this->mot_de_passe;
    }

    public function setMot_de_passe(string $mot_de_passe): self
    {
        $this->mot_de_passe = $mot_de_passe;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $role = null;

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $photo_url = null;

    public function getPhoto_url(): ?string
    {
        return $this->photo_url;
    }

    public function setPhoto_url(?string $photo_url): self
    {
        $this->photo_url = $photo_url;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $verification_code = null;

    public function getVerification_code(): ?string
    {
        return $this->verification_code;
    }

    public function setVerification_code(?string $verification_code): self
    {
        $this->verification_code = $verification_code;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $is_verified = null;

    public function is_verified(): ?bool
    {
        return $this->is_verified;
    }

    public function setIs_verified(?bool $is_verified): self
    {
        $this->is_verified = $is_verified;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $last_login_ip = null;

    public function getLast_login_ip(): ?string
    {
        return $this->last_login_ip;
    }

    public function setLast_login_ip(?string $last_login_ip): self
    {
        $this->last_login_ip = $last_login_ip;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $last_login_location = null;

    public function getLast_login_location(): ?string
    {
        return $this->last_login_location;
    }

    public function setLast_login_location(?string $last_login_location): self
    {
        $this->last_login_location = $last_login_location;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $created_at = null;

    public function getCreated_at(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreated_at(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $photo_file_name = null;

    public function getPhoto_file_name(): ?string
    {
        return $this->photo_file_name;
    }

    public function setPhoto_file_name(?string $photo_file_name): self
    {
        $this->photo_file_name = $photo_file_name;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $face_embedding = null;

    public function getFace_embedding(): ?string
    {
        return $this->face_embedding;
    }

    public function setFace_embedding(?string $face_embedding): self
    {
        $this->face_embedding = $face_embedding;
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Budget::class, mappedBy: 'user')]
    private Collection $budgets;

    /**
     * @return Collection<int, Budget>
     */
    public function getBudgets(): Collection
    {
        if (!$this->budgets instanceof Collection) {
            $this->budgets = new ArrayCollection();
        }
        return $this->budgets;
    }

    public function addBudget(Budget $budget): self
    {
        if (!$this->getBudgets()->contains($budget)) {
            $this->getBudgets()->add($budget);
        }
        return $this;
    }

    public function removeBudget(Budget $budget): self
    {
        $this->getBudgets()->removeElement($budget);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: DeleteNotification::class, mappedBy: 'user')]
    private Collection $deleteNotifications;

    /**
     * @return Collection<int, DeleteNotification>
     */
    public function getDeleteNotifications(): Collection
    {
        if (!$this->deleteNotifications instanceof Collection) {
            $this->deleteNotifications = new ArrayCollection();
        }
        return $this->deleteNotifications;
    }

    public function addDeleteNotification(DeleteNotification $deleteNotification): self
    {
        if (!$this->getDeleteNotifications()->contains($deleteNotification)) {
            $this->getDeleteNotifications()->add($deleteNotification);
        }
        return $this;
    }

    public function removeDeleteNotification(DeleteNotification $deleteNotification): self
    {
        $this->getDeleteNotifications()->removeElement($deleteNotification);
        return $this;
    }
    

    #[ORM\OneToMany(targetEntity: Destination::class, mappedBy: 'user')]
    private Collection $destinations;

    /**
     * @return Collection<int, Destination>
     */
    public function getDestinations(): Collection
    {
        if (!$this->destinations instanceof Collection) {
            $this->destinations = new ArrayCollection();
        }
        return $this->destinations;
    }

    public function addDestination(Destination $destination): self
    {
        if (!$this->getDestinations()->contains($destination)) {
            $this->getDestinations()->add($destination);
        }
        return $this;
    }

    public function removeDestination(Destination $destination): self
    {
        $this->getDestinations()->removeElement($destination);
        return $this;
    }

    #[ORM\OneToMany(targetEntity: Hebergement::class, mappedBy: 'user')]
    private Collection $hebergements;

    /**
     * @return Collection<int, Hebergement>
     */
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

    #[ORM\OneToMany(targetEntity: Paiement::class, mappedBy: 'user')]
    private Collection $paiements;

    /**
     * @return Collection<int, Paiement>
     */
    public function getPaiements(): Collection
    {
        if (!$this->paiements instanceof Collection) {
            $this->paiements = new ArrayCollection();
        }
        return $this->paiements;
    }

    public function addPaiement(Paiement $paiement): self
    {
        if (!$this->getPaiements()->contains($paiement)) {
            $this->getPaiements()->add($paiement);
        }
        return $this;
    }

    public function removePaiement(Paiement $paiement): self
    {
        $this->getPaiements()->removeElement($paiement);
        return $this;
    }

    #[ORM\ManyToMany(targetEntity: Voyage::class, inversedBy: 'users')]
    #[ORM\JoinTable(
        name: 'participation',
        joinColumns: [
            new ORM\JoinColumn(name: 'id', referencedColumnName: 'id')
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'id_voyage', referencedColumnName: 'id_voyage', onDelete: 'CASCADE')
        ]
    )]
    private Collection $voyages;

    public function __construct()
    {
        $this->budgets = new ArrayCollection();
        $this->deleteNotifications = new ArrayCollection();
        $this->destinations = new ArrayCollection();
        $this->hebergements = new ArrayCollection();
        $this->paiements = new ArrayCollection();
        $this->voyages = new ArrayCollection();
    }

    /**
     * @return Collection<int, Voyage>
     */
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
            $voyage->addUser($this);
        }

        return $this;
    }

    public function removeVoyage(Voyage $voyage): self
    {
        if ($this->getVoyages()->removeElement($voyage)) {
            $voyage->removeUser($this);
        }

        return $this;
    }

    public function getDateNaissance(): ?\DateTime
    {
        return $this->date_naissance;
    }

    public function setDateNaissance(\DateTime $date_naissance): static
    {
        $this->date_naissance = $date_naissance;

        return $this;
    }

    public function getMotDePasse(): ?string
    {
        return $this->mot_de_passe;
    }

    public function setMotDePasse(string $mot_de_passe): static
    {
        $this->mot_de_passe = $mot_de_passe;

        return $this;
    }

    public function getPhotoUrl(): ?string
    {
        return $this->photo_url;
    }

    public function setPhotoUrl(?string $photo_url): static
    {
        $this->photo_url = $photo_url;

        return $this;
    }

    public function getVerificationCode(): ?string
    {
        return $this->verification_code;
    }

    public function setVerificationCode(?string $verification_code): static
    {
        $this->verification_code = $verification_code;

        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->is_verified;
    }

    public function setIsVerified(?bool $is_verified): static
    {
        $this->is_verified = $is_verified;

        return $this;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->last_login_ip;
    }

    public function setLastLoginIp(?string $last_login_ip): static
    {
        $this->last_login_ip = $last_login_ip;

        return $this;
    }

    public function getLastLoginLocation(): ?string
    {
        return $this->last_login_location;
    }

    public function setLastLoginLocation(?string $last_login_location): static
    {
        $this->last_login_location = $last_login_location;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTime $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getPhotoFileName(): ?string
    {
        return $this->photo_file_name;
    }

    public function setPhotoFileName(?string $photo_file_name): static
    {
        $this->photo_file_name = $photo_file_name;

        return $this;
    }

    public function getFaceEmbedding(): ?string
    {
        return $this->face_embedding;
    }

    public function setFaceEmbedding(?string $face_embedding): static
    {
        $this->face_embedding = $face_embedding;

        return $this;
    }

}
