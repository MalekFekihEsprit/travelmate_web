<?php

namespace App\Entity;

use App\Repository\NoteDestinationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NoteDestinationRepository::class)]
#[ORM\Table(name: 'note_destination', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_note_destination_user', columns: ['id_destination', 'id_user'])])]
class NoteDestination
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id_note_destination = null;

    #[ORM\ManyToOne(targetEntity: Destination::class, inversedBy: 'notesDestination')]
    #[ORM\JoinColumn(name: 'id_destination', referencedColumnName: 'id_destination', nullable: false, onDelete: 'CASCADE')]
    private ?Destination $destination = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notesDestination')]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'float')]
    private ?float $note = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
    }

    public function getIdNoteDestination(): ?int
    {
        return $this->id_note_destination;
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

    public function getNote(): ?float
    {
        return $this->note;
    }

    public function setNote(float $note): self
    {
        $this->note = $note;

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
}
