<?php

namespace Gemvc\GraphQL;

use Gemvc\Http\JsonResponse;

/**
 * Spec GraphQL HTTP envelope: top-level {@code data} / {@code errors} only.
 *
 * GEMVC {@see JsonResponse} fields (response_code, message, …) stay on the object
 * for Bootstrap status handling but are not serialized to the client.
 */
final class GraphQlJsonResponse extends JsonResponse
{
    /**
     * @var list<array<string, mixed>>|null
     */
    public ?array $graphQlErrors = null;

    /**
     * @param array<string, mixed>|null $data
     * @param list<array<string, mixed>>|null $errors
     */
    public static function spec(?array $data, ?array $errors): JsonResponse
    {
        $response = new self();
        $response->graphQlErrors = ($errors !== null && $errors !== []) ? $errors : null;

        return $response->success($data);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $out = [
            'data' => $this->data,
        ];
        if ($this->graphQlErrors !== null) {
            $out['errors'] = $this->graphQlErrors;
        }

        return $out;
    }
}
