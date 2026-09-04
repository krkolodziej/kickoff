<?php

declare(strict_types=1);

namespace App\Domain\Organization;

use App\Entity\Fixture;
use App\Entity\League;
use App\Entity\MatchEvent;
use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\OrganizationRole;
use App\Entity\Player;
use App\Entity\RosterEntry;
use App\Entity\Season;
use App\Entity\SeasonTeam;
use App\Entity\Team;
use App\Entity\User;
use App\Exception\ConflictException;
use App\Exception\OwnerMembershipIsProtectedException;
use App\Repository\OrganizationMembershipRepository;
use App\Repository\OrganizationRepository;
use App\Repository\UserRepository;
use App\Service\SlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Everything that has to be true about organizations and their members, in one place that
 * knows nothing about HTTP.
 *
 * No Request, no Response, no security token. The controller has already established *who*
 * is asking and *whether they may*; this class only knows what the rules are.
 */
final class OrganizationManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly OrganizationRepository $organizations,
        private readonly OrganizationMembershipRepository $memberships,
        private readonly UserRepository $users,
        private readonly SlugGenerator $slugGenerator,
    ) {
    }

    /**
     * Creates the organization and the creator's ownership together.
     *
     * Both, or neither: an organization with no owner is a row nobody on earth has authority
     * over, so it can neither be administered nor deleted, and only a migration could clean
     * it up.
     *
     * Worth being precise about what the transaction adds, because it is less than it looks:
     * a single flush() is already atomic — Doctrine wraps every commit in a transaction of
     * its own — so the two inserts would stand or fall together regardless. What
     * wrapInTransaction() buys here is that the slug lookup and the insert are one unit of
     * work with an explicit boundary.
     *
     * What it does *not* buy is safety against two people creating the same name at the same
     * instant. Without SELECT … FOR UPDATE both transactions can read the slug as free, and
     * the unique index is what stops the second one — as an integrity violation, which today
     * surfaces as a 500. Rare enough to accept for now; the locking pattern that fixes it
     * arrives with fixture generation, where the race is routine rather than theoretical.
     */
    public function create(User $creator, string $name, ?string $requestedSlug): OrganizationMembership
    {
        return $this->entityManager->wrapInTransaction(
            function () use ($creator, $name, $requestedSlug): OrganizationMembership {
                $slug = $this->resolveSlug($name, $requestedSlug);

                $organization = new Organization($name, $slug, $creator);
                $membership = new OrganizationMembership($organization, $creator, OrganizationRole::Owner);

                $this->entityManager->persist($organization);
                $this->entityManager->persist($membership);

                return $membership;
            },
        );
    }

    public function rename(Organization $organization, string $name, ?string $requestedSlug): void
    {
        $organization->setName($name);

        if (null !== $requestedSlug && $requestedSlug !== $organization->getSlug()) {
            $organization->setSlug($this->resolveSlug($name, $requestedSlug));
        }

        $this->entityManager->flush();
    }

    /**
     * Deleting an organization deletes everything inside it, **in an order chosen here**.
     *
     * `remove()` on its own is not enough, and the reason is a rule from an earlier stage
     * doing its job. A match event points at the player who scored with ON DELETE RESTRICT,
     * deliberately: deleting one player must not quietly erase his goals from the record.
     *
     * Cascading from the organization would reach players and events by two different paths
     * and the database is free to take them in any order — so it sometimes removes a player
     * while his goals still exist and refuses the whole delete. The failure is not even
     * reliable, which is worse than a failure that always happens.
     *
     * So the children go first, deepest first, in one transaction. The RESTRICT still guards
     * the case it was written for: one player, deleted on his own, is still refused.
     */
    public function delete(Organization $organization): void
    {
        $this->entityManager->wrapInTransaction(function () use ($organization): void {
            $seasonIds = $this->seasonIdsOf($organization);

            if ([] !== $seasonIds) {
                $this->deleteWhereSeasonIn(MatchEvent::class, 'e', 'e.fixture IN (SELECT f.id FROM '.Fixture::class.' f WHERE f.season IN (:seasons))', $seasonIds);
                $this->deleteWhereSeasonIn(Fixture::class, 'f', 'f.season IN (:seasons)', $seasonIds);
                $this->deleteWhereSeasonIn(RosterEntry::class, 'r', 'r.seasonTeam IN (SELECT st.id FROM '.SeasonTeam::class.' st WHERE st.season IN (:seasons))', $seasonIds);
                $this->deleteWhereSeasonIn(SeasonTeam::class, 'st', 'st.season IN (:seasons)', $seasonIds);
                $this->deleteWhereSeasonIn(Season::class, 's', 's.id IN (:seasons)', $seasonIds);
            }

            foreach ([League::class, Player::class, Team::class] as $entity) {
                $this->entityManager->createQueryBuilder()
                    ->delete($entity, 'x')
                    ->where('x.organization = :organization')
                    ->setParameter('organization', $organization)
                    ->getQuery()
                    ->execute();
            }

            // Memberships and notifications hang off the organization directly and cascade
            // cleanly, so the row itself can go last through the ORM.
            $this->entityManager->remove($organization);
            $this->entityManager->flush();
        });
    }

    /**
     * @return list<int>
     */
    private function seasonIdsOf(Organization $organization): array
    {
        /** @var list<array{id: int}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('s.id AS id')
            ->from(Season::class, 's')
            ->innerJoin('s.league', 'l')
            ->where('l.organization = :organization')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * @param class-string $entity
     * @param list<int>    $seasonIds
     */
    private function deleteWhereSeasonIn(string $entity, string $alias, string $where, array $seasonIds): void
    {
        $this->entityManager->createQueryBuilder()
            ->delete($entity, $alias)
            ->where($where)
            ->setParameter('seasons', $seasonIds)
            ->getQuery()
            ->execute();
    }

    /**
     * Adds an existing account to the organization.
     *
     * There is no invitation flow yet, so the address must already belong to somebody. That
     * is reported as a validation error on the `email` field rather than as a 404, because
     * from the caller's point of view it is the value they typed that is wrong.
     */
    public function addMember(Organization $organization, string $email, OrganizationRole $role): OrganizationMembership
    {
        $user = $this->users->findOneByEmail($email);

        if (null === $user) {
            throw new ValidationFailedException(
                $email,
                new ConstraintViolationList([
                    new ConstraintViolation(
                        message: 'No account uses this email address yet.',
                        messageTemplate: null,
                        parameters: [],
                        root: $email,
                        propertyPath: 'email',
                        invalidValue: $email,
                    ),
                ]),
            );
        }

        if ($this->memberships->existsFor($organization, $user)) {
            throw new ConflictException('That person is already a member.', 'already_a_member');
        }

        $membership = new OrganizationMembership($organization, $user, $role);

        $this->entityManager->persist($membership);
        $this->entityManager->flush();

        return $membership;
    }

    public function changeRole(OrganizationMembership $membership, OrganizationRole $role): void
    {
        $this->guardOwner($membership);

        $membership->setRole($role);
        $this->entityManager->flush();
    }

    public function removeMember(OrganizationMembership $membership): void
    {
        $this->guardOwner($membership);

        $this->entityManager->remove($membership);
        $this->entityManager->flush();
    }

    /**
     * Both write paths on a membership go through here, so the invariant is stated once.
     * Two separate checks in two controllers is how one of them ends up missing.
     */
    private function guardOwner(OrganizationMembership $membership): void
    {
        if ($membership->isOwner()) {
            throw new OwnerMembershipIsProtectedException();
        }
    }

    private function resolveSlug(string $name, ?string $requestedSlug): string
    {
        $source = null !== $requestedSlug && '' !== trim($requestedSlug) ? $requestedSlug : $name;

        return $this->slugGenerator->uniqueSlug(
            $source,
            fn (string $candidate): bool => $this->organizations->slugExists($candidate),
        );
    }
}
