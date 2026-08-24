<?php

declare(strict_types=1);

use App\Controller\GraphQlController;
use Gemvc\GraphQL\JsonResponseBridge;
use Gemvc\Http\Request;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;

/**
 * App-owned GraphQL schema. Return Schema or callable(Request): Schema.
 *
 * Resolvers call Controller (then Model). Do not use Table here.
 */
return static function (Request $request): Schema {
    $query = new ObjectType([
        'name' => 'Query',
        'fields' => [
            'hello' => [
                'type' => Type::nonNull(Type::string()),
                'description' => 'Sample — GraphQlController::hello(), no Table',
                'resolve' => static function () use ($request): string {
                    $response = (new GraphQlController($request))->hello();
                    $data = JsonResponseBridge::dataOrThrow($response);

                    return is_string($data) ? $data : 'gemvc';
                },
            ],
            // Reuse an existing REST controller (needs DB), e.g.:
            // 'user' => [
            //     'type' => Type::string(),
            //     'args' => ['id' => Type::nonNull(Type::int())],
            //     'resolve' => static function ($root, array $args) use ($request): mixed {
            //         $request->post['id'] = $args['id'];
            //         $response = (new \App\Controller\UserController($request))->read();
            //         return \Gemvc\GraphQL\JsonResponseBridge::dataOrThrow($response);
            //     },
            // ],
        ],
    ]);

    return new Schema(['query' => $query]);
};
