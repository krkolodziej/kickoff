<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Fixture\FixtureGenerator;
use App\Dto\Input\GenerateFixturesRequest;
use App\Dto\Output\FixtureResource;
use App\Entity\MatchStatus;
use App\Repository\FixtureRepository;
use App\Scope\SeasonScope;
use App\Security\Voter\OrganizationVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/organizations/{organizationId<\d+>}/leagues/{leagueId<\d+>}/seasons/{seasonId<\d+>}/fixtures')]
final class FixtureController extends AbstractController
{
    public function __construct(
        private readonly FixtureGenerator $generator,
        private readonly FixtureRepository $fixtures,
    ) {
    }

    #[Route('', name: 'api_fixtures_list', methods: ['GET'])]
    #[IsGranted(OrganizationVoter::VIEW, subject: 'scope')]
    public function list(SeasonScope $scope, Request $request): JsonResponse
    {
        $round = $request->query->get('round');
        $team = $request->query->get('team');

        $rows = $this->fixtures->findForSeason(
            $scope->season(),
            null === $round ? null : $request->query->getInt('round'),
            null === $team ? null : $request->query->getInt('team'),
            $this->statuses($request->query->getString('status')),
        );

        return $this->json(array_map(FixtureResource::fromEntity(...), $rows));
    }

    /**
     * Generating is a POST to its own sub-resource rather than a PUT on the collection,
     * because it is not "here is a calendar, store it" — it is "work one out".
     */
    #[Route('/generate', name: 'api_fixtures_generate', methods: ['POST'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function generate(
        SeasonScope $scope,
        #[MapRequestPayload] GenerateFixturesRequest $payload = new GenerateFixturesRequest(),
    ): JsonResponse {
        $created = $this->generator->generate(
            $scope->season(),
            $payload->doubleRound,
            $payload->firstRoundOn,
            $payload->daysBetweenRounds,
        );

        return $this->json(array_map(FixtureResource::fromEntity(...), $created), Response::HTTP_CREATED);
    }

    /**
     * `?status=LIVE,FINISHED`.
     *
     * An unrecognised value is refused rather than ignored, for the same reason an unknown
     * `order` field is: a filter that quietly does nothing returns a plausible answer to a
     * question nobody asked.
     *
     * @return list<MatchStatus>
     */
    private function statuses(string $raw): array
    {
        if ('' === trim($raw)) {
            return [];
        }

        $statuses = [];

        foreach (explode(',', $raw) as $value) {
            $status = MatchStatus::tryFrom(strtoupper(trim($value)));

            if (null === $status) {
                throw new BadRequestHttpException(\sprintf(
                    'Unknown status "%s". Try one of: %s.',
                    trim($value),
                    implode(', ', MatchStatus::values()),
                ));
            }

            $statuses[] = $status;
        }

        return $statuses;
    }

    /**
     * Deleting the calendar is a separate, deliberate act — which is what lets `generate`
     * refuse to run twice instead of quietly replacing what is already there.
     */
    #[Route('', name: 'api_fixtures_clear', methods: ['DELETE'])]
    #[IsGranted(OrganizationVoter::MANAGE, subject: 'scope')]
    public function clear(SeasonScope $scope): JsonResponse
    {
        $this->generator->clear($scope->season());

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
