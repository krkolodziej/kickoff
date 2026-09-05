<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Pins the authentication contract: status codes, the error envelope, and where the two
 * tokens live. Every later stage builds on this shape, so it is worth over-testing here.
 */
final class AuthApiTest extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
    }

    public function testHealthIsPublic(): void
    {
        $this->client->request('GET', '/api/v1/health');

        self::assertResponseIsSuccessful();
        self::assertSame(['status' => 'ok'], $this->json());
    }

    public function testRegisterCreatesAnAccountAndSignsItIn(): void
    {
        $this->post('/api/v1/auth/register', [
            'email' => 'Ada@Kickoff.test',
            'password' => 'correct-horse-battery',
            'password_confirm' => 'correct-horse-battery',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $body = $this->json();
        self::assertArrayHasKey('token', $body);
        self::assertSame('ada@kickoff.test', $body['user']['email'], 'The address is stored lower-cased.');
        self::assertSame('Ada Lovelace', $body['user']['full_name']);
        self::assertArrayNotHasKey('password', $body['user']);

        // The refresh token must never be readable from JavaScript, and must not be sent
        // along with ordinary API calls.
        $cookie = $this->client->getCookieJar()->get('refresh_token', '/api/v1/token');
        self::assertNotNull($cookie, 'Registration should set the refresh cookie.');
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame('/api/v1/token', $cookie->getPath());

        // The body must not repeat the refresh token: that would put it back within reach
        // of any script on the page, defeating the httpOnly cookie entirely.
        self::assertArrayNotHasKey('refresh_token', $body);
    }

    public function testRegisterRejectsMismatchedPasswordsWithSnakeCaseFieldKeys(): void
    {
        $this->post('/api/v1/auth/register', [
            'email' => 'grace@kickoff.test',
            'password' => 'correct-horse-battery',
            'password_confirm' => 'something-else',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $body = $this->json();
        self::assertSame('validation_error', $body['code']);
        // The PHP property is $passwordConfirm. If this key ever comes back camelCased the
        // frontend stops attaching the message to the field and shows nothing at all.
        self::assertArrayHasKey('password_confirm', $body['fields']);
    }

    public function testRegisterRejectsADuplicateEmailRegardlessOfCase(): void
    {
        UserFactory::createOne(['email' => 'taken@kickoff.test']);

        $this->post('/api/v1/auth/register', [
            'email' => 'TAKEN@Kickoff.test',
            'password' => 'correct-horse-battery',
            'password_confirm' => 'correct-horse-battery',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertArrayHasKey('email', $this->json()['fields']);
    }

    public function testRegisterRejectsAnUnreadableBody(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/auth/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"email": ',
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertSame('invalid_payload', $this->json()['code']);
    }

    public function testLoginReturnsATokenAndTheProfile(): void
    {
        $user = UserFactory::createOne(['email' => 'alan@kickoff.test']);

        $this->post('/api/v1/auth/login', [
            'email' => 'alan@kickoff.test',
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        self::assertResponseIsSuccessful();

        $body = $this->json();
        self::assertNotEmpty($body['token']);
        self::assertSame($user->getId(), $body['user']['id']);
    }

    public function testLoginWithTheWrongPasswordUsesTheSharedEnvelope(): void
    {
        UserFactory::createOne(['email' => 'alan@kickoff.test']);

        $this->post('/api/v1/auth/login', [
            'email' => 'alan@kickoff.test',
            'password' => 'not-the-password',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(
            ['detail' => 'Invalid credentials.', 'code' => 'invalid_credentials'],
            $this->json(),
        );
    }

    public function testLoginDoesNotRevealWhetherTheAccountExists(): void
    {
        UserFactory::createOne(['email' => 'alan@kickoff.test']);

        $this->post('/api/v1/auth/login', ['email' => 'alan@kickoff.test', 'password' => 'wrong']);
        $knownAccount = $this->json();

        $this->post('/api/v1/auth/login', ['email' => 'nobody@kickoff.test', 'password' => 'wrong']);
        $unknownAccount = $this->json();

        self::assertSame($knownAccount, $unknownAccount);
    }

    public function testAnonymousRequestsAreRejectedWithoutABasicAuthChallenge(): void
    {
        $this->client->request('GET', '/api/v1/auth/me');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame(
            ['detail' => 'Authentication is required.', 'code' => 'authentication_required'],
            $this->json(),
        );
        // A `WWW-Authenticate: Basic` header makes the browser open its own credentials
        // dialog on top of the SPA, which users read as the application having frozen.
        self::assertResponseNotHasHeader('WWW-Authenticate');
    }

    public function testAForgedTokenIsDistinguishedFromAMissingOne(): void
    {
        $this->client->request('GET', '/api/v1/auth/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer not.a.token',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertSame('token_invalid', $this->json()['code']);
    }

    public function testTheAccessTokenGrantsAccessToTheProfile(): void
    {
        $user = UserFactory::createOne(['email' => 'alan@kickoff.test']);
        $token = $this->signIn('alan@kickoff.test');

        $this->client->request('GET', '/api/v1/auth/me', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame($user->getId(), $this->json()['id']);
    }

    public function testTheRefreshCookieAloneBuysANewAccessToken(): void
    {
        UserFactory::createOne(['email' => 'alan@kickoff.test']);
        $this->signIn('alan@kickoff.test');

        // No Authorization header: the cookie is the whole credential. This is what lets a
        // page reload restore the session without the token ever being persisted in JS.
        $this->client->request('POST', '/api/v1/token/refresh');

        self::assertResponseIsSuccessful();
        self::assertNotEmpty($this->json()['token']);
    }

    public function testLoggingOutRevokesTheRefreshToken(): void
    {
        UserFactory::createOne(['email' => 'alan@kickoff.test']);
        $token = $this->signIn('alan@kickoff.test');

        $this->client->request('POST', '/api/v1/auth/logout', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // The access token still works until it expires — it is a signature, not a session
        // row — but nothing can mint a replacement any more.
        $this->client->getCookieJar()->clear();
        $this->client->request('POST', '/api/v1/token/refresh');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $uri, array $payload): void
    {
        $this->client->request(
            'POST',
            $uri,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    /**
     * The limiter counts per address as well as per account, so these tests are given
     * addresses of their own.
     *
     * Without that, exhausting the count here would exhaust it for every other test in the
     * suite that signs in from the default 127.0.0.1 — which is how a single new test can
     * turn a hundred unrelated ones red. Both are in the range reserved for documentation.
     */
    private const THROTTLE_IP = '203.0.113.7';
    private const FUMBLE_IP = '203.0.113.8';

    /**
     * The counter lives in a cache pool, not in the database, so `ResetDatabase` does not
     * touch it and it survives from one run of the suite to the next. Left alone, these tests
     * would pass once and then fail for the rest of the minute — and, worse, pass again later
     * without anybody changing anything.
     */
    private function forgetPreviousAttempts(): void
    {
        self::getContainer()->get('cache.rate_limiter')->clear();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postFrom(string $ip, string $uri, array $payload): void
    {
        $this->client->request(
            'POST',
            $uri,
            server: ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => $ip],
            content: json_encode($payload, \JSON_THROW_ON_ERROR),
        );
    }

    private function signIn(string $email): string
    {
        $this->post('/api/v1/auth/login', [
            'email' => $email,
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);

        return $this->json()['token'];
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

    /**
     * The one attack this endpoint exists to invite.
     *
     * Six attempts against one address, and the sixth is refused before the password is even
     * looked at. The response says so plainly — 429 with its own code — rather than repeating
     * "invalid credentials", which would tell a person their correct password was wrong and
     * tell a script to keep going.
     *
     * A fresh address is used so the counter starts at zero regardless of what the rest of
     * this file did: the limiter counts per address as well as per account, and it outlives a
     * single test.
     */
    public function testRepeatedWrongPasswordsAreThrottled(): void
    {
        $this->forgetPreviousAttempts();
        $user = UserFactory::createOne(['email' => 'throttled@kickoff.test']);

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->postFrom(self::THROTTLE_IP, '/api/v1/auth/login', [
                'email' => $user->getEmail(),
                'password' => 'not-the-password',
            ]);

            self::assertResponseStatusCodeSame(
                Response::HTTP_UNAUTHORIZED,
                \sprintf('attempt %d should be a plain refusal', $attempt),
            );
        }

        $this->postFrom(self::THROTTLE_IP, '/api/v1/auth/login', [
            'email' => $user->getEmail(),
            'password' => 'not-the-password',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_TOO_MANY_REQUESTS);
        self::assertSame('too_many_attempts', $this->json()['code']);
    }

    /**
     * And it must not lock out the person who simply mistyped: the limiter is reset by a
     * successful sign-in, so being wrong twice costs nothing afterwards.
     */
    public function testGettingItRightClearsTheCount(): void
    {
        $this->forgetPreviousAttempts();
        $user = UserFactory::createOne(['email' => 'fumbling@kickoff.test']);

        foreach ([1, 2] as $ignored) {
            $this->postFrom(self::FUMBLE_IP, '/api/v1/auth/login', [
                'email' => $user->getEmail(),
                'password' => 'wrong',
            ]);
        }

        $this->postFrom(self::FUMBLE_IP, '/api/v1/auth/login', [
            'email' => $user->getEmail(),
            'password' => UserFactory::DEFAULT_PASSWORD,
        ]);
        self::assertResponseIsSuccessful();

        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $this->postFrom(self::FUMBLE_IP, '/api/v1/auth/login', [
                'email' => $user->getEmail(),
                'password' => 'wrong',
            ]);

            self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        }
    }
}
