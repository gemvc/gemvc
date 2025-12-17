<?php
namespace Gemvc\Http;
use Gemvc\Http\JsonResponse;
/**
 * create and return consistent JSON responses in API
 */
class Response {

    public static function success(mixed $data ,?int $count = null , ?string $service_message = null):JsonResponse
    {
        return (new JsonResponse())->success($data ,$count,$service_message);
    }
    public static function updated(mixed $data ,?int $count = null,?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'updated: '.$service_message : 'updated';
        return (new JsonResponse())->updated($data ,$count,$service_message);
    }

    public static function created(mixed $data ,?int $count = null,?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'created: '.$service_message : 'created';
        return (new JsonResponse())->created($data ,$count,$service_message);    
    }

    public static function successButNoContentToShow(mixed $data ,?int $count = null,?string $service_message= null):JsonResponse
    {
         $service_message = $service_message ? 'success but no content to show: '.$service_message : 'success no content';
         return (new JsonResponse())->successButNoContentToShow($data ,$count,$service_message);
    }
    
    public static function deleted(mixed $data ,?int $count = null,?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'deleted: '.$service_message: 'deleted';
        return (new JsonResponse())->deleted($data ,$count,$service_message);
    }
    public static function unauthorized(?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'unauthorized: '.$service_message : 'unauthorized';
        return (new JsonResponse())->unauthorized($service_message);
    }
    public static function forbidden(?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'forbidden: '.$service_message : 'forbidden';
        return (new JsonResponse())->forbidden($service_message);
    }
    public static function notFound(?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'not found: '.$service_message : 'not found';
        return (new JsonResponse())->notFound($service_message);
    }

    public static function internalError(?string $service_message = null ):JsonResponse
    {
        $service_message = $service_message ? 'internal error: '.$service_message : 'internal error';
        return (new JsonResponse())->internalError($service_message);
    }

    public static function unknownError(mixed $data, ?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'unknown error: '.$service_message : 'unknown error';
        return (new JsonResponse())->unknownError($data, $service_message);
    }

    public static function notAcceptable(?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'not acceptable: '.$service_message : 'not acceptable';
        return (new JsonResponse())->notAcceptable($service_message);
    }

    public static function conflict(?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'conflict: '.$service_message : 'conflict';
        return (new JsonResponse())->conflict($service_message);
    }

    public static function unsupportedMediaType(?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'unsupported media type: '.$service_message : 'unsupported media type';
        return (new JsonResponse())->unsupportedMediaType($service_message);
    }

    public static function unprocessableEntity(?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'unprocessable entity: '.$service_message : 'unprocessable entity';
        return (new JsonResponse())->unprocessableEntity($service_message);
    }
    
    public static function badRequest(?string $service_message = null):JsonResponse
    {
        $service_message = $service_message ? 'bad request: '.$service_message : 'bad request';
        return (new JsonResponse())->badRequest($service_message);
    }
}