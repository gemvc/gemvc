<?php

declare(strict_types=1);

use App\Controller\GraphQlController;
use Gemvc\GraphQL\JsonResponseBridge;
use Gemvc\Http\Request;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * Copy → app/graphql/schema.php
 *
 * Must return GraphQL\Type\Schema or callable(Request): Schema.
 * Resolvers: Controller → Model. Never Table.
 */
return static function (Request $request): Schema {
    $query = new ObjectType([
        'name' => 'Query',
        'fields' => [
            'hello' => [
                'type' => Type::nonNull(Type::string()),
                'resolve' => static function () use ($request): string {
                    $response = (new GraphQlController($request))->hello();
                    $data = JsonResponseBridge::dataOrThrow($response);

                    return is_string($data) ? $data : 'gemvc';
                },
            ],
        ],
    ]);

    return new Schema(['query' => $query]);
};
