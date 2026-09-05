<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Command\SeedDemoCommand;
use App\Entity\Organization;
use App\Entity\OrganizationRole;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMembershipFactory;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * An endpoint that signs somebody in without a credential deserves to be read suspiciously,
 * so this file is mostly about what it will not do.
 *
 * `WebTestCase` rather than `ApiTestCase`, because the flag is read when the kernel boots and
 * these tests need to boot it both ways.
 */
final class DemoApiTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    public function testItDoesNotExistWhenItIsSwitchedOff(): void
    {
        $this->bootWithDemo(false);
        $this->seedVisitor();

        $this->client->request('POST', '/api/v1/auth/demo');

        // 404, not 403: a disabled endpoint has no reason to announce that it exists.
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Switched on but never seeded is a deployment that has not finished setting itself up.
     * Saying so beats a 404, which would send somebody looking for a bug in the routing.
     */
    public function testItSaysSoWhenTheDataIsNotThereYet(): void
    {
        $this->bootWithDemo(true);

        $this->client->request('POST', '/api/v1/auth/demo');

        self::assertResponseStatusCodeSame(Response::HTTP_SERVICE_UNAVAILABLE);
        self::assertSame('demo_not_ready', $this->json()['code']);
    }

    public function testItSignsInWithoutAPassword(): void
    {
        $this->bootWithDemo(true);
        $this->seedVisitor();

        $this->client->request('POST', '/api/v1/auth/demo');

        self::assertResponseIsSuccessful();

        $body = $this->json();
        self::assertNotSame('', $body['token']);
        self::assertSame(SeedDemoCommand::VISITOR_EMAIL, $body['user']['email']);

        // The same response the login endpoint produces, refresh cookie included, so the
        // client has nothing special to do afterwards.
        $cookie = $this->client->getCookieJar()->get('refresh_token', '/api/v1/token');
        self::assertNotNull($cookie);
    }

    /**
     * The property the whole design rests on.
     *
     * Anyone on the internet can press this button, so the account behind it must not be able
     * to destroy what it opens. Deleting an organization needs OWNER; the visitor is an
     * administrator, so everything worth demonstrating is open and this one thing is not.
     */
    public function testTheVisitorCannotDeleteTheOrganization(): void
    {
        $this->bootWithDemo(true);
        $organization = $this->seedVisitor();

        $this->client->request('POST', '/api/v1/auth/demo');
        $token = $this->json()['token'];

        $this->client->request('DELETE', '/api/v1/organizations/'.$organization->getId(), server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * And everything else is open, or the demonstration would show nothing.
     */
    public function testTheVisitorMayStillRunTheLeague(): void
    {
        $this->bootWithDemo(true);
        $organization = $this->seedVisitor();

        $this->client->request('POST', '/api/v1/auth/demo');
        $token = $this->json()['token'];

        $this->client->request(
            'POST',
            '/api/v1/organizations/'.$organization->getId().'/leagues',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token],
            content: json_encode(['name' => 'Cup'], \JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
    }

    private function bootWithDemo(bool $enabled): void
    {
        $_SERVER['DEMO_LOGIN_ENABLED'] = $enabled ? '1' : '0';
        $_ENV['DEMO_LOGIN_ENABLED'] = $_SERVER['DEMO_LOGIN_ENABLED'];

        $this->client = self::createClient();
    }

    /**
     * The visitor and an organization to visit — not the whole demonstration league, which
     * plays out seventy-odd matches and would say nothing more about this endpoint.
     */
    private function seedVisitor(): Organization
    {
        $owner = UserFactory::createOne();
        $organization = OrganizationFactory::createOne(['createdBy' => $owner]);

        $visitor = UserFactory::createOne(['email' => SeedDemoCommand::VISITOR_EMAIL]);
        OrganizationMembershipFactory::createOne([
            'organization' => $organization,
            'user' => $visitor,
            'role' => OrganizationRole::Admin,
        ]);

        return $organization;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        return json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
    }
}
