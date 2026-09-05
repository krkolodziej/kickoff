<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Organization;
use App\Entity\User;
use App\Tests\Factory\LeagueFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * A client that already has the answer should be told so, not sent it again.
 */
final class ConditionalRequestTest extends ApiTestCase
{
    private User $owner;
    private Organization $organization;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = UserFactory::createOne();
        $this->organization = OrganizationFactory::createOne(['createdBy' => $this->owner]);
        LeagueFactory::createOne(['organization' => $this->organization, 'name' => 'District League']);

        $this->token = $this->signIn($this->owner);
    }

    public function testAnUnchangedReadAnswers304WithNoBody(): void
    {
        $this->request('GET', $this->uri(), null, $this->token);
        self::assertResponseIsSuccessful();

        $etag = $this->client->getResponse()->headers->get('ETag');
        self::assertNotNull($etag, 'a read should carry a tag to come back with');

        $this->client->request('GET', $this->uri(), server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
            'HTTP_IF_NONE_MATCH' => $etag,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_MODIFIED);
        self::assertSame('', $this->client->getResponse()->getContent());
    }

    /**
     * The tag is computed from the bytes, so it cannot claim nothing changed when something
     * did. This is the property that makes the weak version of this safe.
     */
    public function testTheTagChangesWhenTheAnswerDoes(): void
    {
        $this->request('GET', $this->uri(), null, $this->token);
        $before = $this->client->getResponse()->headers->get('ETag');

        $this->request('POST', $this->uri(), ['name' => 'Youth League'], $this->token);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->request('GET', $this->uri(), null, $this->token);
        $after = $this->client->getResponse()->headers->get('ETag');

        self::assertNotSame($before, $after);
    }

    /**
     * Two people reading the same URL get different answers, so the response must never be
     * held in a cache that is shared between them.
     */
    public function testTaggedAnswersAreMarkedPrivate(): void
    {
        $this->request('GET', $this->uri(), null, $this->token);

        self::assertStringContainsString(
            'private',
            (string) $this->client->getResponse()->headers->get('Cache-Control'),
        );
    }

    /**
     * A write is reporting what just happened. "Has this changed since last time?" is not a
     * question about it, and tagging it would invite a client to ask.
     */
    public function testWritesAreNotTagged(): void
    {
        $this->request('POST', $this->uri(), ['name' => 'Reserve League'], $this->token);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertNull($this->client->getResponse()->headers->get('ETag'));
    }

    private function uri(): string
    {
        return '/api/v1/organizations/'.$this->organization->getId().'/leagues';
    }
}
