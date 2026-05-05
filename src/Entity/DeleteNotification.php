<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\DeleteNotificationRepository;

#[ORM\Entity(repositoryClass: DeleteNotificationRepository::class)]
#[ORM\Table(name: 'delete_notifications')]
class DeleteNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_notification = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'deleteNotifications')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'admin_id', referencedColumnName: 'id', nullable: true)]
    private ?User $admin = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $user_name = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $admin_name = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $item_type = null;

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $item_id = null;

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $item_name = null;

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $custom_reason = null;

    // ✅ FIX CRITIQUE : nullable: true + valeur par défaut null
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deleted_at = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $is_read = null;

    // ---- Getters / Setters ----

    public function getIdNotification(): ?int
    {
        return $this->id_notification;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getAdmin(): ?User
    {
        return $this->admin;
    }

    public function setAdmin(?User $admin): static
    {
        $this->admin = $admin;
        return $this;
    }

    public function getUserName(): ?string
    {
        return $this->user_name;
    }

    public function setUserName(string $user_name): static
    {
        $this->user_name = $user_name;
        return $this;
    }

    public function getAdminName(): ?string
    {
        return $this->admin_name;
    }

    public function setAdminName(string $admin_name): static
    {
        $this->admin_name = $admin_name;
        return $this;
    }

    public function getItemType(): ?string
    {
        return $this->item_type;
    }

    public function setItemType(string $item_type): static
    {
        $this->item_type = $item_type;
        return $this;
    }

    public function getItemId(): ?int
    {
        return $this->item_id;
    }

    public function setItemId(int $item_id): static
    {
        $this->item_id = $item_id;
        return $this;
    }

    public function getItemName(): ?string
    {
        return $this->item_name;
    }

    public function setItemName(string $item_name): static
    {
        $this->item_name = $item_name;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getCustomReason(): ?string
    {
        return $this->custom_reason;
    }

    public function setCustomReason(?string $custom_reason): static
    {
        $this->custom_reason = $custom_reason;
        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deleted_at;
    }

    // ✅ FIX : paramètre nullable pour permettre la réinitialisation
    public function setDeletedAt(?\DateTimeInterface $deleted_at): static
    {
        $this->deleted_at = $deleted_at;
        return $this;
    }

    public function isRead(): ?bool
    {
        return $this->is_read;
    }

    public function setIsRead(?bool $is_read): static
    {
        $this->is_read = $is_read;
        return $this;
    }
}