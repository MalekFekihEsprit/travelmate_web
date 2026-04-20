<?php

namespace App\Entity;

use App\Repository\ParticipationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\Table(name: 'participation')]
class Participation
{
    public const DEFAULT_ROLE = 'Participant';

    public const ROLES = [
        self::DEFAULT_ROLE,
        'Organisateur',
        'Observateur',
    ];

    public const SELECTABLE_ROLES = [
        self::DEFAULT_ROLE,
        'Observateur',
    ];

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Voyage::class, inversedBy: 'participations')]
    #[ORM\JoinColumn(name: 'id_voyage', referencedColumnName: 'id_voyage', nullable: false, onDelete: 'CASCADE')]
    private ?Voyage $voyage = null;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => self::DEFAULT_ROLE])]
    private string $role_participation = self::DEFAULT_ROLE;

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

    public function getRole_participation(): string
    {
        return $this->role_participation;
    }

    public function setRole_participation(string $role_participation): self
    {
        $this->role_participation = $role_participation;

        return $this;
    }

    public function getRoleParticipation(): string
    {
        return $this->role_participation;
    }

    public function setRoleParticipation(string $role_participation): self
    {
        $this->role_participation = $role_participation;

        return $this;
    }

    public static function getAvailableRoles(): array
    {
        return self::ROLES;
    }

    public static function getSelectableRoles(): array
    {
        return self::SELECTABLE_ROLES;
    }

    public static function isSelectableRole(string $role): bool
    {
        return in_array($role, self::SELECTABLE_ROLES, true);
    }
}