<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\ApiExceptionInterface;
use App\Http\ApiErrorResponse;
use App\Http\ViolationFormatter;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Every failure under /api leaves through here, so there is exactly one place that decides
 * what an error looks like on the wire.
 *
 * Priority 0 puts this after the security firewall's own exception listener, which is what
 * turns an AuthenticationException into a call to our entry point. When that has already
 * produced a response there is nothing left to do, hence the early return.
 */
#[AsEventListener(event: KernelEvents::EXCEPTION, priority: 0)]
final class ApiExceptionSubscriber
{
    public function __construct(
        private readonly ViolationFormatter $violationFormatter,
        private readonly LoggerInterface $logger,
        private readonly bool $debug,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }

        if (null !== $event->getResponse()) {
            return;
        }

        $event->setResponse($this->toResponse($event->getThrowable()));
    }

    private function toResponse(\Throwable $exception): ApiErrorResponse
    {
        // Rules the domain enforces on purpose. They carry their own status and code.
        if ($exception instanceof ApiExceptionInterface) {
            return new ApiErrorResponse(
                $exception->getStatusCode(),
                $exception->getMessage(),
                $exception->getErrorCode(),
                $exception->getFields(),
            );
        }

        // MapRequestPayload reports two entirely different kinds of failure and wraps both
        // in an HttpException. Unwrap before matching, or malformed JSON escapes as a bare
        // 400 carrying Symfony's own message instead of ours.
        $cause = $exception instanceof HttpExceptionInterface ? $exception->getPrevious() : null;

        $validationFailure = $this->unwrap($exception, $cause, ValidationFailedException::class);

        if ($validationFailure instanceof ValidationFailedException) {
            return new ApiErrorResponse(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Request validation failed.',
                'validation_error',
                $this->violationFormatter->format($validationFailure->getViolations()),
            );
        }

        $denormalisationFailure = $this->unwrap($exception, $cause, PartialDenormalizationException::class);

        if ($denormalisationFailure instanceof PartialDenormalizationException) {
            return new ApiErrorResponse(
                Response::HTTP_BAD_REQUEST,
                'The request body could not be read.',
                'invalid_payload',
                $this->denormalisationFields($denormalisationFailure),
            );
        }

        if ($exception instanceof AccessDeniedException) {
            return new ApiErrorResponse(Response::HTTP_FORBIDDEN, $exception->getMessage(), 'permission_denied');
        }

        if ($exception instanceof AuthenticationException) {
            return new ApiErrorResponse(Response::HTTP_UNAUTHORIZED, 'Authentication is required.', 'authentication_required');
        }

        if ($exception instanceof HttpExceptionInterface) {
            $status = $exception->getStatusCode();

            return new ApiErrorResponse($status, $this->messageFor($status, $exception), self::codeFor($status));
        }

        // Anything reaching this point is a bug. Log it with the stack trace, and tell the
        // client nothing beyond the fact that it failed.
        $this->logger->error('Unhandled API exception: {message}', [
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        return new ApiErrorResponse(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $this->debug ? $exception->getMessage() : 'Something went wrong on our side.',
            'server_error',
        );
    }

    /**
     * @template T of \Throwable
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function unwrap(\Throwable $exception, ?\Throwable $cause, string $class): ?\Throwable
    {
        if ($exception instanceof $class) {
            return $exception;
        }

        return $cause instanceof $class ? $cause : null;
    }

    private function messageFor(int $status, \Throwable $exception): string
    {
        $message = $exception->getMessage();

        // Symfony's own 404 and 405 messages quote the request URI and the routing table,
        // which is internal detail an API has no reason to hand out. Everything else
        // carries a message somebody wrote deliberately, so it is passed through.
        if ('' !== $message && !\in_array($status, [Response::HTTP_NOT_FOUND, Response::HTTP_METHOD_NOT_ALLOWED], true)) {
            return $message;
        }

        return match ($status) {
            Response::HTTP_NOT_FOUND => 'Not found.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Method not allowed.',
            Response::HTTP_BAD_REQUEST => 'The request could not be read.',
            default => 'Request failed.',
        };
    }

    private static function codeFor(int $status): string
    {
        return match ($status) {
            Response::HTTP_BAD_REQUEST => 'invalid_payload',
            Response::HTTP_UNAUTHORIZED => 'authentication_required',
            Response::HTTP_FORBIDDEN => 'permission_denied',
            Response::HTTP_NOT_FOUND => 'not_found',
            Response::HTTP_METHOD_NOT_ALLOWED => 'method_not_allowed',
            Response::HTTP_CONFLICT => 'conflict',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'validation_error',
            Response::HTTP_TOO_MANY_REQUESTS => 'rate_limited',
            default => 'api_error',
        };
    }

    /**
     * @return array<string, list<string>>
     */
    private function denormalisationFields(PartialDenormalizationException $exception): array
    {
        $fields = [];

        foreach ($exception->getErrors() as $error) {
            $path = $error->getPath() ?? '_';
            $expected = implode(' or ', $error->getExpectedTypes() ?? ['a valid value']);
            $fields[$path][] = sprintf('Expected %s, got %s.', $expected, $error->getCurrentType() ?? 'nothing');
        }

        return $fields;
    }
}
