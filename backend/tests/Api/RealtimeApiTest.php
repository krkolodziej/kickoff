<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Fixture;
use App\Entity\Organization;
use App\Entity\Season;
use App\Entity\User;
use App\Tests\Factory\LeagueFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\SeasonFactory;
use App\Tests\Factory\SeasonTeamFactory;
use App\Tests\Factory\TeamFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * The hub cannot tell a member from a stranger — it only checks that a subscriber's token
 * names the topic being asked for. So everything worth testing about realtime authorisation
 * happens here, before a token exists at all.
 */
final class RealtimeApiTest extends ApiTestCase
{
    private User $owner;
    private Organization $organization;
    private Season $season;
    private Fixture $fixture;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = UserFactory::createOne();
        $this->organization = OrganizationFactory::createOne(['createdBy' => $this->owner]);
        $league = LeagueFactory::createOne(['organization' => $this->organization]);
        $this->season = SeasonFactory::createOne(['league' => $league, 'name' => '2026']);

        $home = TeamFactory::createOne(['organization' => $this->organization, 'name' => 'Stal']);
        $away = TeamFactory::createOne(['organization' => $this->organization, 'name' => 'Resovia']);
        SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $home]);
        SeasonTeamFactory::createOne(['season' => $this->season, 'team' => $away]);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->fixture = new Fixture($this->season, $home, $away, 1, 1);
        $entityManager->persist($this->fixture);
        $entityManager->flush();
    }

    public function testAMemberIsToldWhereToListenAndGivenACookieToDoIt(): void
    {
        $this->request('POST', $this->uri(), null, $this->signIn($this->owner));

        self::assertResponseIsSuccessful();

        $body = $this->json();
        self::assertSame('/matches/'.$this->fixture->getId(), $body['topic']);
        self::assertNotSame('', $body['hub']);

        $cookie = $this->client->getResponse()->headers->getCookies()[0] ?? null;
        self::assertNotNull($cookie, 'the token travels in a cookie, because EventSource cannot set headers');
        self::assertSame('mercureAuthorization', $cookie->getName());
        self::assertTrue($cookie->isHttpOnly(), 'and it is not readable from JavaScript, like the refresh token');
    }

    /**
     * The token names one topic. A token wide enough to be convenient outlives the reason it
     * was issued, so this checks the claim rather than trusting the call that built it.
     */
    public function testTheTokenNamesOnlyThisMatch(): void
    {
        $this->request('POST', $this->uri(), null, $this->signIn($this->owner));

        $cookie = $this->client->getResponse()->headers->getCookies()[0];
        $claims = self::claimsOf((string) $cookie->getValue());

        self::assertArrayHasKey('subscribe', $claims['mercure']);
        self::assertSame(
            ['/matches/'.$this->fixture->getId()],
            $claims['mercure']['subscribe'],
        );
        self::assertArrayNotHasKey('publish', $claims['mercure'], 'a browser may listen, not broadcast');
    }

    public function testAStrangerIsNotEvenToldTheMatchExists(): void
    {
        $stranger = UserFactory::createOne();

        $this->request('POST', $this->uri(), null, $this->signIn($stranger));

        // 404 rather than 403, and rather than a token for a topic that would then be empty:
        // the scope resolves the match through the caller's own membership, so a stranger
        // never reaches the point where a token is minted.
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testItNeedsAnAccount(): void
    {
        $this->request('POST', $this->uri());

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @return array{mercure: array{subscribe?: list<string>, publish?: list<string>}}
     */
    private static function claimsOf(string $jwt): array
    {
        $parts = explode('.', $jwt);
        self::assertCount(3, $parts, 'a JWT has three parts');

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);
        self::assertIsString($payload);

        /** @var array{mercure: array{subscribe?: list<string>, publish?: list<string>}} $claims */
        $claims = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        return $claims;
    }

    private function uri(): string
    {
        return \sprintf(
            '/api/v1/organizations/%d/leagues/%d/seasons/%d/fixtures/%d/realtime',
            $this->organization->getId(),
            $this->season->getLeague()->getId(),
            $this->season->getId(),
            $this->fixture->getId(),
        );
    }
}
