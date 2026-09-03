<?php

declare(strict_types=1);

namespace App\Http\ValueResolver;

use App\Entity\User;
use App\Scope\LeagueScope;
use App\Scope\OrganizationScope;
use App\Scope\ScopeFactory;
use App\Scope\ScopeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Type-hint a scope in a controller and it appears, already proven.
 *
 * This is Symfony 7's replacement for SensioFrameworkExtraBundle's ParamConverter, and it
 * is a better one: instead of "fetch the entity named by this route parameter", it answers
 * "resolve this whole route path into an object the caller is entitled to hold". The
 * controller signature
 *
 *     public function show(OrganizationScope $scope): JsonResponse
 *
 * carries the authorization decision in its types. There is no lookup to forget, no filter
 * to omit, and nothing to copy into the next controller incorrectly.
 *
 * Route parameters are read from the request attributes rather than declared as method
 * arguments, so `{organizationId}` never appears in a controller signature at all.
 */
final class ScopeValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ScopeFactory $scopeFactory,
        private readonly Security $security,
    ) {
    }

    /**
     * @return iterable<ScopeInterface>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $type = $argument->getType();

        if (null === $type || !is_a($type, ScopeInterface::class, true)) {
            return [];
        }

        $user = $this->security->getUser();

        if (!$user instanceof User) {
            // Unreachable behind the firewall, which never lets an anonymous request through
            // to a controller. Kept because a scope without a user has no meaning, and a
            // silent null here would become a much more confusing error further down.
            throw new NotFoundHttpException();
        }

        yield match ($type) {
            OrganizationScope::class => $this->scopeFactory->organizationScope(
                $user,
                $this->routeId($request, 'organizationId'),
            ),
            LeagueScope::class => $this->scopeFactory->leagueScope(
                $user,
                $this->routeId($request, 'organizationId'),
                $this->routeId($request, 'leagueId'),
            ),
            default => throw new \LogicException(\sprintf('No factory is registered for the scope "%s".', $type)),
        };
    }

    /**
     * Route requirements already restrict these to digits, so a non-numeric value never
     * reaches here — but reading it defensively keeps the failure a 404 rather than a
     * TypeError if a requirement is ever dropped.
     */
    private function routeId(Request $request, string $name): int
    {
        $value = $request->attributes->get($name);

        if (!is_numeric($value)) {
            throw new NotFoundHttpException();
        }

        return (int) $value;
    }
}
