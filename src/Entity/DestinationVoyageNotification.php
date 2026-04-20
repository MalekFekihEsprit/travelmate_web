<?php

namespace App\Entity;

use App\Repository\DestinationVoyageNotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DestinationVoyageNotificationRepository::class)]
#[ORM\Table(
    name: 'destination_voyage_notification',
    indexes: [
        new ORM\Index(name: 'IDX_94E0F2D56B3CA4B', columns: ['id_user']),
        new ORM\Index(name: 'IDX_94E0F2D5D3E90A14', columns: ['id_voyage']),
    ],
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_destination_voyage_notification', columns: ['id_user', 'id_voyage']),
    ]
)]
class DestinationVoyageNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_notification = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Voyage::class)]
    #[ORM\JoinColumn(name: 'id_voyage', referencedColumnName: 'id_voyage', nullable: false, onDelete: 'CASCADE')]
    private ?Voyage $voyage = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $is_dismissed = false;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
    }

    public function getIdNotification(): ?int
    {
        return $this->id_notification;
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

    public function getVoyage(): ?Voyage
    {
        return $this->voyage;
    }

    public function setVoyage(?Voyage $voyage): self
    {
        $this->voyage = $voyage;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function isDismissed(): bool
    {
        return $this->is_dismissed;
    }

    public function setIsDismissed(bool $is_dismissed): self
    {
        $this->is_dismissed = $is_dismissed;

        return $this;
    }
}
