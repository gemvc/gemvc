<?php

namespace Gemvc\GraphQL;

use Gemvc\Http\JsonResponse;

/**
 * Unwrap a Controller/Model {@see JsonResponse} inside a GraphQL resolver.
 *
 * HTTP 4xx/5xx become a client-safe GraphQL error. Do not query Table from resolvers.
 */
final class JsonResponseBridge
{
    public static function dataOrThrow(JsonResponse $response): mixed
    {
        if ($response->response_code >= 400) {
            $message = $response->service_message ?? $response->message;
            if ($message === '') {
                $message = 'Request failed';
            }
            if (class_exists(\GraphQL\Error\UserError::class)) {
                throw new \GraphQL\Error\UserError($message);
            }
            throw new \RuntimeException($message);
        }

        return $response->data;
    }
}
