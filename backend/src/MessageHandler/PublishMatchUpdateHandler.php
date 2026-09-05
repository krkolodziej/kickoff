<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\MatchUpdated;
use App\Realtime\MatchTopic;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Puts a signal on the hub. Deliberately not the data.
 *
 * The payload is `{"fixture_id": 41}` and nothing else, which is a decision rather than
 * laziness:
 *
 * - **Authorisation stays in one place.** A topic is a coarse thing — you may subscribe to it
 *   or you may not. The REST API already decides, per request and per role, what a caller is
 *   allowed to see. Pushing the match itself through the hub would mean maintaining a second,
 *   weaker answer to that question, and the two would drift.
 * - **The client already knows how to read a match.** It has a cache keyed by fixture; the
 *   signal tells it that its copy is stale and it refetches through the endpoint it would have
 *   polled anyway. The saving is the three-second wait, not the request.
 * - **The stream cannot go stale.** A payload describes a moment; a signal describes a fact.
 *   Two updates arriving out of order leave the client fetching twice, not rendering the older
 *   one.
 *
 * The update is private: only a subscriber holding a token that names this topic receives it.
 */
#[AsMessageHandler]
final readonly class PublishMatchUpdateHandler
{
    public function __construct(private HubInterface $hub)
    {
    }

    public function __invoke(MatchUpdated $message): void
    {
        $this->hub->publish(new Update(
            topics: MatchTopic::forId($message->fixtureId),
            data: json_encode(['fixture_id' => $message->fixtureId], \JSON_THROW_ON_ERROR),
            private: true,
        ));
    }
}
