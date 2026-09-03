<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrganizationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * The tenant. Everything else in the application — leagues, clubs, players, matches —
 * belongs to exactly one of these, and every query is scoped through a membership in it.
 */
#[ORM\Entity(repositoryClass: OrganizationRepository::class)]
#[ORM\Table(name: 'organizations')]
#[ORM\UniqueConstraint(name: 'uniq_organization_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class Organization
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name;

    /**
     * Kept short on purpose. Under utf8mb4 InnoDB allows 3072 bytes per index, so a
     * generous VARCHAR here would start to hurt once slugs appear in composite unique
     * indexes alongside a foreign key — which they do from the next stage onwards.
     */
    #[ORM\Column(length: 64)]
    private string $slug;

    /**
     * RESTRICT rather than CASCADE: deleting an account must not silently take an entire
     * organization — and everything inside it — with it.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private User $createdBy;

    /**
     * `persist` in the cascade because a membership only ever exists as part of an
     * organization, and `remove` because the organization is what gives it meaning:
     * deleting one must not leave rows pointing at nothing.
     *
     * @var Collection<int, OrganizationMembership>
     */
    #[ORM\OneToMany(targetEntity: OrganizationMembership::class, mappedBy: 'organization', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $memberships;

    public function __construct(string $name, string $slug, User $createdBy)
    {
        $this->name = $name;
        $this->slug = $slug;
        $this->createdBy = $createdBy;
        $this->memberships = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    /**
     * @return Collection<int, OrganizationMembership>
     */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(OrganizationMembership $membership): static
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
        }

        return $this;
    }
}
