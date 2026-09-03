<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The one and only error shape this API emits.
 *
 *     { "detail": "...", "code": "...", "fields": { "email": ["..."] } }
 *
 * `detail` is for humans, `code` is for the client's `switch`, `fields` is present only
 * when the failure is attributable to individual inputs. Keeping this in one class means
 * the contract is greppable, and the frontend can trust it everywhere.
 */
final class ApiErrorResponse extends JsonResponse
{
    /**
     * @param array<string, list<string>> $fields
     */
    public function __construct(int $status, string $detail, string $code, array $fields = [])
    {
        $payload = ['detail' => $detail, 'code' => $code];

        if ([] !== $fields) {
            $payload['fields'] = $fields;
        }

        parent::__construct($payload, $status);
    }
}
