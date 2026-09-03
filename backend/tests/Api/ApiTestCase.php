<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\User;
use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Shared plumbing for the HTTP-level tests: sign in, send JSON, read JSON back.
 */
abstract class ApiTestCase extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    protected function request(string $method, string $uri, ?array $payload = null, ?string $token = null): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: null === $payload ? null : json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Signs in for real, through the login endpoint, rather than with `loginUser()`.
     *
     * `loginUser()` fabricates a security token in the test kernel and would skip the very
     * thing these tests exist to exercise: that a bearer token issued by this application
     * is accepted by its own firewall.
     */
    protected function signIn(User $user): string
    {
        $this->request('POST', '/api/v1/auth/login', [
            'email' => $user->getEmail(),
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        return $this->json()['token'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(): array
    {
        return json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function jsonList(): array
    {
        /* @var list<array<string, mixed>> */
        return json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
    }
}
