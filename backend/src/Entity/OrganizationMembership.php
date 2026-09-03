<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrganizationMembershipRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Who belongs to which organization, and with what authority.
 *
 * This row is the security boundary of the whole application. Every scoped query joins it,
 * so an organization a user has no membership in is not merely forbidden — it is invisible,
 * and the API answers 404 rather than 403.
 */
#[ORM\Entity(repositoryClass: OrganizationMembershipRepository::class)]
#[ORM\Table(name: 'organization_memberships')]
#[ORM\UniqueConstraint(name: 'uniq_membership_organization_user', columns: ['organization_id', 'user_id'])]
#[ORM\HasLifecycleCallbacks]
class OrganizationMembership
{
    use TimestampsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * Stored as its backing string, so the column reads as `OWNER` rather than as an
     * integer nobody can interpret from a database client.
     */
    #[ORM\Column(length: 10, enumType: OrganizationRole::class)]
    private OrganizationRole $role;

    public function __construct(Organization $organization, User $user, OrganizationRole $role)
    {
        $this->organization = $organization;
        $this->user = $user;
        $this->role = $role;

        $organization->addMembership($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getRole(): OrganizationRole
    {
        return $this->role;
    }

    public function setRole(OrganizationRole $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function isOwner(): bool
    {
        return OrganizationRole::Owner === $this->role;
    }
}
