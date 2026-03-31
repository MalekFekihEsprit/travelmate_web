<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\DeleteNotificationRepository;

#[ORM\Entity(repositoryClass: DeleteNotificationRepository::class)]
#[ORM\Table(name: 'delete_notifications')]
class DeleteNotification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_notification = null;

    public function getId_notification(): ?int
    {
        return $this->id_notification;
    }

    public function setId_notification(int $id_notification): self
    {
        $this->id_notification = $id_notification;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'deleteNotifications')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
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

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $user_name = null;

    public function getUser_name(): ?string
    {
        return $this->user_name;
    }

    public function setUser_name(string $user_name): self
    {
        $this->user_name = $user_name;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'deleteNotifications')]
    #[ORM\JoinColumn(name: 'admin_id', referencedColumnName: 'id')]


    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $admin_name = null;

    public function getAdmin_name(): ?string
    {
        return $this->admin_name;
    }

    public function setAdmin_name(string $admin_name): self
    {
        $this->admin_name = $admin_name;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $item_type = null;

    public function getItem_type(): ?string
    {
        return $this->item_type;
    }

    public function setItem_type(string $item_type): self
    {
        $this->item_type = $item_type;
        return $this;
    }

    #[ORM\Column(type: 'integer', nullable: false)]
    private ?int $item_id = null;

    public function getItem_id(): ?int
    {
        return $this->item_id;
    }

    public function setItem_id(int $item_id): self
    {
        $this->item_id = $item_id;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: false)]
    private ?string $item_name = null;

    public function getItem_name(): ?string
    {
        return $this->item_name;
    }

    public function setItem_name(string $item_name): self
    {
        $this->item_name = $item_name;
        return $this;
    }

    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $reason = null;

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $custom_reason = null;

    public function getCustom_reason(): ?string
    {
        return $this->custom_reason;
    }

    public function setCustom_reason(?string $custom_reason): self
    {
        $this->custom_reason = $custom_reason;
        return $this;
    }

    #[ORM\Column(type: 'datetime', nullable: false)]
    private ?\DateTimeInterface $deleted_at = null;

    public function getDeleted_at(): ?\DateTimeInterface
    {
        return $this->deleted_at;
    }

    public function setDeleted_at(\DateTimeInterface $deleted_at): self
    {
        $this->deleted_at = $deleted_at;
        return $this;
    }

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $is_read = null;

    public function is_read(): ?bool
    {
        return $this->is_read;
    }

    public function setIs_read(?bool $is_read): self
    {
        $this->is_read = $is_read;
        return $this;
    }

    public function getIdNotification(): ?int
    {
        return $this->id_notification;
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

    public function getCustomReason(): ?string
    {
        return $this->custom_reason;
    }

    public function setCustomReason(?string $custom_reason): static
    {
        $this->custom_reason = $custom_reason;

        return $this;
    }

    public function getDeletedAt(): ?\DateTime
    {
        return $this->deleted_at;
    }

    public function setDeletedAt(\DateTime $deleted_at): static
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
